<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Category */
class CategoryResource extends JsonResource
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
            'slug' => Str::slug($this->resource->name),
            'active' => ! $this->resource->trashed(),
            'version' => $this->resource->version,
        ];
    }
}
