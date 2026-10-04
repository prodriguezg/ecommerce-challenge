<?php

namespace Tests\Unit\Jobs;

use App\Enums\PaymentOutcome;
use App\Jobs\DeliverPaymentWebhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverPaymentWebhookTest extends TestCase
{
    public function test_delivers_bearer_authenticated_card_free_webhook(): void
    {
        config([
            'payment.webhook_token' => 'secret-test-token',
            'payment.webhook_connect_timeout_seconds' => 2,
            'payment.webhook_timeout_seconds' => 5,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://commerce.test/api/v1/payments/webhooks/mock' => Http::response(status: 204),
        ]);
        $job = new DeliverPaymentWebhook(
            eventId: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            providerPaymentId: '01ARZ3NDEKTSV4RRFFQ69G5FAY',
            outcome: PaymentOutcome::Declined,
            callbackUrl: 'https://commerce.test/api/v1/payments/webhooks/mock',
            occurredAt: '2026-10-04T12:00:04Z',
        );

        $job->handle();

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer secret-test-token')
                && $request->data() === [
                    'event_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                    'provider_payment_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY',
                    'outcome' => 'declined',
                    'provider_code' => 'CARD_DECLINED',
                    'occurred_at' => '2026-10-04T12:00:04Z',
                ];
        });
    }

    public function test_configures_three_exponential_backoff_retries(): void
    {
        $job = new DeliverPaymentWebhook(
            eventId: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            providerPaymentId: '01ARZ3NDEKTSV4RRFFQ69G5FAY',
            outcome: PaymentOutcome::Error,
            callbackUrl: 'https://commerce.test/api/v1/payments/webhooks/mock',
            occurredAt: '2026-10-04T12:00:04Z',
        );

        $this->assertSame(4, $job->tries);
        $this->assertSame([1, 2, 4], $job->backoff);
    }
}
