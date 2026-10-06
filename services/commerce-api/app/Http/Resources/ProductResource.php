<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
class ProductResource extends JsonResource
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
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'description' => $this->resource->description ?? '',
            'price' => (new Money($this->resource->price, $this->resource->currency->code))
                ->roundedAmount($this->resource->currency->minor_units),
            'weight_kg' => number_format((float) $this->resource->weight_kg, 4, '.', ''),
            'price_excludes_tax' => true,
            'currency' => $this->resource->currency->code,
            'category' => $this->resource->category === null ? null : new CategoryResource($this->resource->category),
            'tax_id' => $this->resource->tax_id,
            'image_url' => $this->resource->image_path === null
                ? '/images/product-placeholder.svg'
                : Storage::url($this->resource->image_path),
            'in_stock' => $this->resource->availableStock() > 0,
            'version' => $this->resource->version,
            'created_at' => $this->resource->created_at->toISOString(),
            'updated_at' => $this->resource->updated_at->toISOString(),
        ];
    }
}
