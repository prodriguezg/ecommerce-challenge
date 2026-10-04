<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\PreventsModification;
use Database\Factories\OrderStateHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class OrderStateHistory extends DomainModel
{
    /** @use HasFactory<OrderStateHistoryFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'metadata' => 'array'];
    }
}
