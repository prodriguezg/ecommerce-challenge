<?php

namespace App\Models;

use App\Enums\ManualReviewStatus;
use Database\Factories\ManualReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class ManualReview extends DomainModel
{
    /** @use HasFactory<ManualReviewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    protected function casts(): array
    {
        return ['status' => ManualReviewStatus::class, 'resolved_at' => 'datetime'];
    }
}
