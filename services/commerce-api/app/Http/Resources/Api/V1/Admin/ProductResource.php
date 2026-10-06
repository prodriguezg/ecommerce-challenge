<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'description' => $this->resource->description ?? '',
            'price' => number_format((float) $this->resource->price, 2, '.', ''),
            'weight_kg' => number_format((float) $this->resource->weight_kg, 4, '.', ''),
            'price_excludes_tax' => true,
            'currency' => $this->resource->currency->code,
            'category' => $this->resource->category === null ? null : new CategoryResource($this->resource->category),
            'tax_id' => $this->resource->tax_id,
            'image_url' => route('products.image', ['product' => $this->resource->getKey()], false),
            'in_stock' => (int) $this->resource->inventory->stock_on_hand > 0,
            'version' => (int) $this->resource->version,
            'created_at' => $this->resource->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->resource->updated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
