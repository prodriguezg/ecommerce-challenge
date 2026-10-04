<?php

namespace App\Models;

use App\Models\Concerns\PreventsModification;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Guarded(['*'])]
class AuditLog extends DomainModel
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'request_context' => 'array'];
    }
}
