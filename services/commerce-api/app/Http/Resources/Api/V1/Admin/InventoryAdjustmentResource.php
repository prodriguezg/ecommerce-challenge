<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'product_id' => $this->resource->product_id,
            'previous_on_hand' => (int) $this->resource->previous_quantity,
            'new_on_hand' => (int) $this->resource->new_quantity,
            'reason' => $this->resource->reason->value,
            'note' => $this->resource->note,
            'actor_id' => $this->resource->actor_user_id,
            'created_at' => $this->resource->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
