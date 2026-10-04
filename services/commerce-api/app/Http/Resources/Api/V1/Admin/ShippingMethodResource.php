<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'amount' => number_format((float) $this->resource->amount, 2, '.', ''),
            'currency' => $this->resource->currency->code,
            'active' => ! $this->resource->trashed(),
            'version' => (int) $this->resource->version,
        ];
    }
}
