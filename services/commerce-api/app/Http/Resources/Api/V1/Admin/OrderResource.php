<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\OrderResource as BaseOrderResource;
use App\Models\Order;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;

/** @mixin Order */
class OrderResource extends BaseOrderResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'stateHistory',
            'reservations.items',
            'payment.stateHistory',
            'payment.webhookEvents',
            'manualReview',
        ]);
        $base = parent::toArray($request);
        $payment = $this->resource->payment;
        $review = $this->resource->manualReview;
        $minorUnits = (int) $this->resource->currency->minor_units;

        return [
            ...$base,
            'customer_email' => $this->resource->email,
            'shipping_method_name' => $this->resource->shipping_method_name,
            'amounts' => [
                'subtotal' => $this->rounded($this->resource->subtotal, $minorUnits),
                'product_tax' => $this->rounded($this->resource->product_tax, $minorUnits),
                'shipping' => $this->rounded($this->resource->shipping_amount, $minorUnits),
                'shipping_tax' => $this->rounded($this->resource->shipping_tax, $minorUnits),
                'total' => $this->rounded($this->resource->grand_total, $minorUnits),
                'currency' => $this->resource->currency_code,
            ],
            'state_history' => $this->resource->stateHistory
                ->sortBy('created_at')
                ->values()
                ->map(fn ($history): array => [
                    'from' => $history->from_status?->value,
                    'to' => $history->to_status->value,
                    'reason' => $history->reason_code,
                    'actor_id' => $history->actor_user_id,
                    'created_at' => $history->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
                ])->all(),
            'reservations' => $this->resource->reservations->map(fn ($reservation): array => [
                'id' => $reservation->id,
                'status' => $reservation->status->value,
                'expires_at' => $reservation->expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'items' => $reservation->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all(),
            ])->all(),
            'payment' => $payment === null ? null : [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'provider_payment_id' => $payment->provider_payment_id,
                'provider_code' => $payment->provider_code,
                'error_code' => $payment->error_code,
                'history' => $payment->stateHistory->sortBy('created_at')->values()->map(fn ($history): array => [
                    'from' => $history->from_status?->value,
                    'to' => $history->to_status->value,
                    'reason' => $history->reason_code,
                    'created_at' => $history->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
                ])->all(),
                'webhook_events' => $payment->webhookEvents->sortBy('received_at')->values()->map(fn ($event): array => [
                    'event_id' => $event->provider_event_id,
                    'outcome' => $event->status,
                    'processing_result' => $event->processing_result,
                    'received_at' => $event->received_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'processed_at' => $event->processed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                ])->all(),
            ],
            'manual_review' => $review === null ? null : [
                'id' => $review->id,
                'reason' => $review->reason_code,
                'status' => $review->status->value,
                'resolution_note' => $review->resolution_note,
                'resolved_by_user_id' => $review->resolved_by_user_id,
                'resolved_at' => $review->resolved_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                'created_at' => $review->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }

    private function rounded(string $amount, int $minorUnits): string
    {
        return (string) BigDecimal::of($amount)->toScale($minorUnits, RoundingMode::HalfUp);
    }
}
