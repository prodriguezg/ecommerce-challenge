<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Guarded([])]
class Product extends DomainModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->name = Str::squish($product->name);
            $product->sku = Str::squish($product->sku);
            $product->normalized_name = Str::lower($product->name);
            $product->normalized_sku = Str::upper($product->sku);
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasOne<Inventory, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /** @return HasMany<InventoryAdjustment, $this> */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return HasMany<OrderLine, $this> */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<ReservationItem, $this> */
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'weight_kg' => 'decimal:4'];
    }
}
