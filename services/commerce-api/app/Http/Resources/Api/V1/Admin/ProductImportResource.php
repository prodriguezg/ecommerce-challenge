<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'status' => $this->resource->rejected_count > 0 ? 'completed_with_rejections' : 'completed',
            'original_filename' => $this->resource->original_filename,
            'mode' => $this->resource->mode->value,
            'unknown_category_policy' => $this->resource->unknown_category_policy->value,
            'stock_override' => (bool) $this->resource->stock_override,
            'stock_override_confirmed_at' => $this->resource->stock_override_confirmed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'total_rows' => (int) $this->resource->total_count,
            'accepted_rows' => (int) $this->resource->imported_count,
            'warning_rows' => (int) $this->resource->warning_count,
            'rejected_rows' => (int) $this->resource->rejected_count,
            'created_at' => $this->resource->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->resource->updated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
