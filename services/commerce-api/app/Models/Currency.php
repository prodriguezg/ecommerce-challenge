<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Guarded([])]
class Currency extends DomainModel
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Currency $currency): void {
            $currency->code = Str::upper(trim($currency->code));
        });
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ShippingMethod, $this> */
    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return ['rate_to_base' => 'decimal:12', 'is_base' => 'boolean', 'rate_updated_at' => 'datetime'];
    }
}
