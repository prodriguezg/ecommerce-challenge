<?php

namespace App\Http\Resources;

use App\Models\ShippingMethod;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShippingMethod */
class ShippingMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'name' => $this->resource->name,
            'amount' => (new Money($this->resource->amount, $this->resource->currency->code))
                ->roundedAmount($this->resource->currency->minor_units),
            'currency' => $this->resource->currency->code,
            'active' => ! $this->resource->trashed(),
            'version' => $this->resource->version,
        ];
    }
}
