<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class WebhookEvent extends DomainModel
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    public const CREATED_AT = 'received_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
