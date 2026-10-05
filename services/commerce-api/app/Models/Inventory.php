<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Inventory extends DomainModel
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<InventoryAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    /** @return HasMany<ReservationItem, $this> */
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function reservedQuantity(): int
    {
        return (int) $this->reservationItems()
            ->whereHas('reservation', fn ($query) => $query->where('status', ReservationStatus::Active->value))
            ->sum('quantity');
    }
}
