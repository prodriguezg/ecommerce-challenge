<?php

namespace App\Models;

use App\Models\Concerns\PreventsModification;
use Database\Factories\OrderAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class OrderAddress extends DomainModel
{
    /** @use HasFactory<OrderAddressFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
