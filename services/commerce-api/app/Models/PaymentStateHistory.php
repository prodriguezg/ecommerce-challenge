<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\PreventsModification;
use Database\Factories\PaymentStateHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class PaymentStateHistory extends DomainModel
{
    /** @use HasFactory<PaymentStateHistoryFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return ['from_status' => PaymentStatus::class, 'to_status' => PaymentStatus::class, 'metadata' => 'array'];
    }
}
