<?php

namespace App\Jobs;

use App\Enums\PaymentOutcome;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DeliverPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [1, 2, 4];

    public int $timeout = 10;

    public function __construct(
        public readonly string $eventId,
        public readonly string $providerPaymentId,
        public readonly PaymentOutcome $outcome,
        public readonly string $callbackUrl,
        public readonly string $occurredAt,
    ) {}

    public function handle(): void
    {
        Http::withToken((string) config('payment.webhook_token'))
            ->acceptJson()
            ->connectTimeout((int) config('payment.webhook_connect_timeout_seconds'))
            ->timeout((int) config('payment.webhook_timeout_seconds'))
            ->post($this->callbackUrl, [
                'event_id' => $this->eventId,
                'provider_payment_id' => $this->providerPaymentId,
                'outcome' => $this->outcome->value,
                'provider_code' => $this->outcome->providerCode(),
                'occurred_at' => $this->occurredAt,
            ])
            ->throw();
    }
}
