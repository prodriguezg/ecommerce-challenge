<?php

namespace App\Services;

use App\Enums\ManualReviewStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\PaymentWebhookConflictException;
use App\Models\Inventory;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderStateHistory;
use App\Models\Payment;
use App\Models\PaymentStateHistory;
use App\Models\Reservation;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentTransitionService
{
    /** @param array{event_id: string, commerce_payment_id: string, provider_payment_id: string, outcome: string, provider_code: string, occurred_at: string} $payload */
    public function process(array $payload): void
    {
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            DB::transaction(function () use ($payload, $payloadHash): void {
                $existing = WebhookEvent::query()
                    ->where('provider_event_id', $payload['event_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof WebhookEvent) {
                    $this->assertDuplicateMatches($existing, $payloadHash);

                    return;
                }

                $payment = Payment::query()->lockForUpdate()->findOrFail($payload['commerce_payment_id']);

                if ($payment->provider_payment_id !== null
                    && ! hash_equals($payment->provider_payment_id, $payload['provider_payment_id'])) {
                    throw new PaymentWebhookConflictException('The provider payment ID does not match the commerce payment.');
                }

                if ($payment->provider_payment_id === null) {
                    $payment->forceFill(['provider_payment_id' => $payload['provider_payment_id']])->save();
                }

                $reservation = Reservation::query()
                    ->where('order_id', $payment->order_id)
                    ->with('items')
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $event = WebhookEvent::query()->create([
                    'provider_event_id' => $payload['event_id'],
                    'payment_id' => $payment->id,
                    'provider_payment_id' => $payload['provider_payment_id'],
                    'event_type' => 'payment.updated',
                    'status' => $payload['outcome'],
                    'payload_hash' => $payloadHash,
                    'metadata' => ['occurred_at' => $payload['occurred_at']],
                ]);

                $result = $payload['outcome'] === 'succeeded'
                    ? $this->applySuccess($payment, $order, $reservation, $payload)
                    : $this->applyFailure($payment, $order, $reservation, $payload);

                $event->forceFill([
                    'processed_at' => $this->databaseNow(),
                    'processing_result' => $result,
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            $existing = WebhookEvent::query()->where('provider_event_id', $payload['event_id'])->first();

            if (! $existing instanceof WebhookEvent) {
                throw $exception;
            }

            $this->assertDuplicateMatches($existing, $payloadHash);
        }
    }

    private function assertDuplicateMatches(WebhookEvent $event, string $payloadHash): void
    {
        if (! hash_equals($event->payload_hash, $payloadHash)) {
            throw new PaymentWebhookConflictException('The provider event ID was already used with a different payload.');
        }
    }

    /** @param array{event_id: string, commerce_payment_id: string, provider_payment_id: string, outcome: string, provider_code: string, occurred_at: string} $payload */
    private function applySuccess(Payment $payment, Order $order, Reservation $reservation, array $payload): string
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return 'duplicate_success_ignored';
        }

        $previousPaymentStatus = $payment->status;
        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'provider_code' => $payload['provider_code'],
            'error_code' => null,
        ])->save();
        $this->paymentHistory($payment, $previousPaymentStatus, PaymentStatus::Succeeded, 'provider_success', $payload['event_id']);

        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::ManualReview, OrderStatus::ReviewProcessed], true)) {
            return 'success_recorded_order_final';
        }

        $databaseNow = $this->databaseNow();

        if ($order->status === OrderStatus::AwaitingPayment
            && $reservation->status === ReservationStatus::Active
            && $reservation->expires_at->greaterThan($databaseNow)) {
            $this->deductReservedStock($reservation);
            $reservation->forceFill([
                'status' => ReservationStatus::Consumed,
                'claim_token' => null,
                'claimed_at' => null,
            ])->save();
            $this->transitionOrder($order, OrderStatus::Paid, 'payment_succeeded_on_time');

            return 'paid_from_reservation';
        }

        if ($reservation->status === ReservationStatus::Active) {
            $reservation->forceFill([
                'status' => ReservationStatus::Expired,
                'claim_token' => null,
                'claimed_at' => null,
            ])->save();

            if ($order->status === OrderStatus::AwaitingPayment) {
                $this->transitionOrder($order, OrderStatus::Expired, 'reservation_expired_before_success');
            }
        }

        if ($this->deductFreshStock($reservation, $databaseNow)) {
            $this->transitionOrder($order, OrderStatus::Paid, 'late_payment_succeeded_with_stock');

            return 'late_success_paid';
        }

        $this->transitionOrder($order, OrderStatus::ManualReview, 'late_payment_insufficient_stock');
        ManualReview::query()->firstOrCreate(
            ['order_id' => $order->id],
            ['reason_code' => 'late_success_insufficient_stock', 'status' => ManualReviewStatus::Pending],
        );

        return 'late_success_manual_review';
    }

    /** @param array{event_id: string, commerce_payment_id: string, provider_payment_id: string, outcome: string, provider_code: string, occurred_at: string} $payload */
    private function applyFailure(Payment $payment, Order $order, Reservation $reservation, array $payload): string
    {
        if ($payment->status === PaymentStatus::Succeeded
            || in_array($order->status, [OrderStatus::Paid, OrderStatus::ManualReview, OrderStatus::ReviewProcessed], true)) {
            return 'out_of_order_failure_ignored';
        }

        $targetStatus = $payload['outcome'] === 'declined' ? PaymentStatus::Declined : PaymentStatus::ProviderError;

        if ($payment->status !== $targetStatus) {
            $previousPaymentStatus = $payment->status;
            $payment->forceFill([
                'status' => $targetStatus,
                'provider_code' => $payload['provider_code'],
                'error_code' => $payload['outcome'] === 'error' ? 'provider_error' : null,
            ])->save();
            $this->paymentHistory($payment, $previousPaymentStatus, $targetStatus, 'provider_'.$payload['outcome'], $payload['event_id']);
        }

        if ($order->status === OrderStatus::AwaitingPayment) {
            if ($reservation->status === ReservationStatus::Active) {
                $reservation->forceFill([
                    'status' => ReservationStatus::Released->value,
                    'claim_token' => null,
                    'claimed_at' => null,
                ])->save();
            }

            $this->transitionOrder($order, OrderStatus::PaymentFailed, 'payment_'.$payload['outcome']);
        }

        return $payload['outcome'] === 'declined' ? 'payment_declined' : 'payment_provider_error';
    }

    private function deductReservedStock(Reservation $reservation): void
    {
        $inventories = Inventory::query()
            ->whereIn('id', $reservation->items->pluck('inventory_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservation->items as $item) {
            /** @var Inventory $inventory */
            $inventory = $inventories->get($item->inventory_id);

            if ($inventory->stock_on_hand < $item->quantity) {
                throw new PaymentWebhookConflictException('Reserved stock is no longer available.');
            }

            $inventory->forceFill([
                'stock_on_hand' => $inventory->stock_on_hand - $item->quantity,
                'version' => $inventory->version + 1,
            ])->save();
        }
    }

    private function deductFreshStock(Reservation $reservation, CarbonImmutable $databaseNow): bool
    {
        $inventories = Inventory::query()
            ->whereIn('id', $reservation->items->pluck('inventory_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservation->items as $item) {
            /** @var Inventory $inventory */
            $inventory = $inventories->get($item->inventory_id);
            $reserved = (int) $inventory->reservationItems()
                ->whereHas('reservation', fn ($query) => $query
                    ->where('status', ReservationStatus::Active->value)
                    ->where('expires_at', '>', $databaseNow))
                ->sum('quantity');

            if ($item->quantity > $inventory->stock_on_hand - $reserved) {
                return false;
            }
        }

        foreach ($reservation->items as $item) {
            /** @var Inventory $inventory */
            $inventory = $inventories->get($item->inventory_id);
            $inventory->forceFill([
                'stock_on_hand' => $inventory->stock_on_hand - $item->quantity,
                'version' => $inventory->version + 1,
            ])->save();
        }

        return true;
    }

    private function transitionOrder(Order $order, OrderStatus $to, string $reason): void
    {
        $from = $order->status;

        if ($from === $to) {
            return;
        }

        $order->forceFill(['status' => $to, 'version' => $order->version + 1])->save();
        $history = new OrderStateHistory;
        $history->forceFill([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason_code' => $reason,
            'metadata' => [],
        ])->save();
    }

    private function paymentHistory(Payment $payment, PaymentStatus $from, PaymentStatus $to, string $reason, string $eventId): void
    {
        $history = new PaymentStateHistory;
        $history->forceFill([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason_code' => $reason,
            'metadata' => ['provider_event_id' => $eventId],
        ])->save();
    }

    private function databaseNow(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) DB::scalar('SELECT CURRENT_TIMESTAMP'))->utc();
    }
}
