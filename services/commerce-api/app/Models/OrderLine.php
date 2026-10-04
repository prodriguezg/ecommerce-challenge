<?php

namespace App\Models;

use App\Models\Concerns\PreventsModification;
use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class OrderLine extends DomainModel
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:4', 'unit_price' => 'decimal:4', 'line_subtotal' => 'decimal:4', 'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:4', 'line_total' => 'decimal:4'];
    }
}
