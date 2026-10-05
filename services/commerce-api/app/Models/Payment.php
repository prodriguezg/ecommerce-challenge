<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property PaymentStatus $status */
#[Guarded([])]
class Payment extends DomainModel
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<PaymentStateHistory, $this> */
    public function stateHistory(): HasMany
    {
        return $this->hasMany(PaymentStateHistory::class);
    }

    /** @return HasMany<WebhookEvent, $this> */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'decimal:4'];
    }
}
