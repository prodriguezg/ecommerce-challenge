<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property OrderStatus $status
 * @property int $version
 */
#[Guarded([])]
class Order extends DomainModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasOne<OrderAddress, $this> */
    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    /** @return HasMany<OrderStateHistory, $this> */
    public function stateHistory(): HasMany
    {
        return $this->hasMany(OrderStateHistory::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasOne<ManualReview, $this> */
    public function manualReview(): HasOne
    {
        return $this->hasOne(ManualReview::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:4',
            'product_tax' => 'decimal:4',
            'shipping_amount' => 'decimal:4',
            'shipping_tax' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'guest_token_expires_at' => 'datetime',
        ];
    }
}
