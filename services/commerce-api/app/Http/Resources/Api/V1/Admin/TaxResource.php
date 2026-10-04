<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'rate' => rtrim(rtrim((string) $this->resource->rate, '0'), '.'),
            'active' => ! $this->resource->trashed(),
            'version' => (int) $this->resource->version,
        ];
    }
}
