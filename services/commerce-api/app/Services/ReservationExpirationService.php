<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderStateHistory;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReservationExpirationService
{
    public function expireBatch(int $limit): int
    {
        $boundedLimit = max(1, min($limit, 1000));
        $claimToken = bin2hex(random_bytes(32));
        $claimedIds = DB::transaction(function () use ($boundedLimit, $claimToken): array {
            $databaseNow = $this->databaseNow();
            $staleBefore = $databaseNow->subSeconds((int) config('api.checkout.reservation_claim_ttl_seconds'));
            $query = Reservation::query()
                ->where('status', ReservationStatus::Active->value)
                ->where('expires_at', '<=', $databaseNow)
                ->where(function (Builder $query) use ($staleBefore): void {
                    $query->whereNull('claim_token')->orWhere('claimed_at', '<=', $staleBefore);
                })
                ->orderBy('expires_at')
                ->orderBy('id')
                ->limit($boundedLimit);

            if (DB::getDriverName() === 'mysql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            } else {
                $query->lockForUpdate();
            }

            $ids = $query->pluck('id');

            if ($ids->isNotEmpty()) {
                Reservation::query()->whereIn('id', $ids)->update([
                    'claim_token' => $claimToken,
                    'claimed_at' => $databaseNow,
                ]);
            }

            return $ids->all();
        }, 3);

        $expired = 0;

        foreach ($claimedIds as $reservationId) {
            $expired += DB::transaction(
                fn (): int => $this->expireClaimed((string) $reservationId, $claimToken),
                3,
            );
        }

        return $expired;
    }

    private function expireClaimed(string $reservationId, string $claimToken): int
    {
        $databaseNow = $this->databaseNow();
        $reservation = Reservation::query()->lockForUpdate()->find($reservationId);

        if (! $reservation instanceof Reservation
            || $reservation->status !== ReservationStatus::Active
            || ! hash_equals((string) $reservation->claim_token, $claimToken)
            || $reservation->expires_at->greaterThan($databaseNow)) {
            return 0;
        }

        $order = Order::query()->lockForUpdate()->findOrFail($reservation->order_id);
        $reservation->forceFill([
            'status' => ReservationStatus::Expired,
            'claim_token' => null,
            'claimed_at' => null,
        ])->save();

        if ($order->status === OrderStatus::AwaitingPayment) {
            $from = $order->status;
            $order->forceFill([
                'status' => OrderStatus::Expired,
                'version' => $order->version + 1,
            ])->save();
            $history = new OrderStateHistory;
            $history->forceFill([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => OrderStatus::Expired,
                'reason_code' => 'reservation_expired',
                'metadata' => [],
            ])->save();
        }

        return 1;
    }

    private function databaseNow(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) DB::scalar('SELECT CURRENT_TIMESTAMP'))->utc();
    }
}
