<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['currency', 'lines', 'address', 'payment']);
        $minorUnits = (int) $this->resource->currency->minor_units;
        $shippingAddress = [
            'name' => $this->resource->address->recipient_name,
            'line1' => $this->resource->address->line_1,
            'city' => $this->resource->address->city,
            'region' => $this->resource->address->region,
            'postal_code' => $this->resource->address->postal_code,
            'country' => $this->resource->address->country_code,
            'phone' => $this->resource->address->phone,
        ];

        if ($this->resource->address->line_2 !== null) {
            $shippingAddress['line2'] = $this->resource->address->line_2;
        }

        return [
            'id' => $this->resource->id,
            'number' => $this->resource->order_code,
            'status' => $this->resource->status->value,
            'payment_status' => $this->paymentStatus(),
            'lines' => $this->resource->lines->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'name' => $line->product_name,
                'quantity' => $line->quantity,
                'unit_price' => $this->rounded($line->unit_price, $minorUnits),
                'line_subtotal' => $this->rounded($line->line_subtotal, $minorUnits),
                'currency' => $line->currency_code,
                'available' => true,
            ])->values(),
            'total' => $this->rounded($this->resource->grand_total, $minorUnits),
            'currency' => $this->resource->currency_code,
            'shipping_address' => $shippingAddress,
            'version' => $this->resource->version,
            'created_at' => $this->resource->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->resource->updated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function paymentStatus(): string
    {
        return match ($this->resource->payment?->status) {
            PaymentStatus::Succeeded => 'succeeded',
            PaymentStatus::Declined => 'declined',
            PaymentStatus::ProviderError, PaymentStatus::InitiationFailed => 'error',
            default => 'pending',
        };
    }

    private function rounded(string $amount, int $minorUnits): string
    {
        return (string) BigDecimal::of($amount)->toScale($minorUnits, RoundingMode::HalfUp);
    }
}
