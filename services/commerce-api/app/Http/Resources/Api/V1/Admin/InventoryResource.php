<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reserved = $this->resource->reservedQuantity();

        return [
            'product_id' => $this->resource->product_id,
            'on_hand' => (int) $this->resource->stock_on_hand,
            'reserved' => $reserved,
            'available' => (int) $this->resource->stock_on_hand - $reserved,
        ];
    }
}
