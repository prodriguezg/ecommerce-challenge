<?php

namespace App\Models;

use Database\Factories\TaxFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Guarded([])]
class Tax extends DomainModel
{
    /** @use HasFactory<TaxFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Tax $tax): void {
            $tax->name = Str::squish($tax->name);
            $tax->normalized_name = Str::lower($tax->name);
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

    protected function casts(): array
    {
        return ['rate' => 'decimal:4'];
    }
}
