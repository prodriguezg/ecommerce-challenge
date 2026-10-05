<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'actor_id' => $this->resource->actor_user_id,
            'action' => $this->resource->action_code,
            'subject_type' => $this->resource->target_type,
            'subject_id' => $this->resource->target_id,
            'metadata' => [
                'before' => $this->resource->before_data,
                'after' => $this->resource->after_data,
            ],
            'created_at' => $this->resource->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
