<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Reservation extends DomainModel
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ReservationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    protected function casts(): array
    {
        return ['status' => ReservationStatus::class, 'expires_at' => 'datetime', 'claimed_at' => 'datetime'];
    }
}
