<?php

namespace App\Models;

use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Guarded([])]
class ShippingMethod extends DomainModel
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (ShippingMethod $method): void {
            $method->name = Str::squish($method->name);
            $method->normalized_name = Str::lower($method->name);
        });
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
