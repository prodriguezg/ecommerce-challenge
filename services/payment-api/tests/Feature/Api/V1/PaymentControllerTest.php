<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentOutcome;
use App\Jobs\DeliverPaymentWebhook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.callback_url' => 'https://commerce.test/api/v1/payments/webhooks/mock',
            'payment.normal_delay_min_seconds' => 4,
            'payment.normal_delay_max_seconds' => 4,
            'payment.late_success_delay_seconds' => 150,
        ]);
        Cache::flush();
    }

    public function test_valid_payment_returns_202_and_dispatches_delayed_webhook_without_card_data(): void
    {
        Queue::fake([DeliverPaymentWebhook::class]);
        $this->travelTo('2026-10-04 12:00:00 UTC');

        $response = $this->postJson('/api/v1/payments', $this->validPayload(), [
            'Idempotency-Key' => 'payment-attempt-0001',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('status', 'processing')
            ->assertJsonStructure(['provider_payment_id', 'status']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $response->json('provider_payment_id'));
        Queue::assertPushed(DeliverPaymentWebhook::class, function (DeliverPaymentWebhook $job): bool {
            $serialized = serialize($job);

            return $job->outcome === PaymentOutcome::Succeeded
                && $job->occurredAt === '2026-10-04T12:00:04Z'
                && ! str_contains($serialized, '4000000000010001');
        });
    }

    public function test_identical_idempotent_replay_returns_original_acceptance_once(): void
    {
        Queue::fake([DeliverPaymentWebhook::class]);
        $headers = ['Idempotency-Key' => 'payment-attempt-0002'];

        $first = $this->postJson('/api/v1/payments', $this->validPayload(), $headers);
        $second = $this->postJson('/api/v1/payments', $this->validPayload(), $headers);

        $first->assertStatus(202);
        $second
            ->assertStatus(202)
            ->assertExactJson($first->json());
        Queue::assertPushed(DeliverPaymentWebhook::class, 1);
    }

    public function test_same_idempotency_key_with_different_payload_returns_409(): void
    {
        Queue::fake([DeliverPaymentWebhook::class]);
        $headers = ['Idempotency-Key' => 'payment-attempt-0003'];
        $this->postJson('/api/v1/payments', $this->validPayload(), $headers)->assertStatus(202);

        $response = $this->postJson('/api/v1/payments', [
            ...$this->validPayload(),
            'amount' => '20.00',
        ], $headers);

        $response
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        Queue::assertPushed(DeliverPaymentWebhook::class, 1);
    }

    #[DataProvider('cardOutcomes')]
    public function test_exact_test_number_maps_to_documented_outcome_and_delay(
        string $testNumber,
        PaymentOutcome $outcome,
        int $delay,
    ): void {
        Queue::fake([DeliverPaymentWebhook::class]);
        $this->travelTo('2026-10-04 12:00:00 UTC');

        $this->postJson('/api/v1/payments', [
            ...$this->validPayload(),
            'payment_test_number' => $testNumber,
        ], ['Idempotency-Key' => 'payment-'.$testNumber])->assertStatus(202);

        Queue::assertPushed(
            DeliverPaymentWebhook::class,
            fn (DeliverPaymentWebhook $job): bool => $job->outcome === $outcome
                && $job->occurredAt === now()->addSeconds($delay)->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /** @return array<string, array{string, PaymentOutcome, int}> */
    public static function cardOutcomes(): array
    {
        return [
            'success' => ['4000000000010001', PaymentOutcome::Succeeded, 4],
            'decline' => ['4000000000000002', PaymentOutcome::Declined, 4],
            'provider error' => ['4000000000090003', PaymentOutcome::Error, 4],
            'late success' => ['4000000000080004', PaymentOutcome::Succeeded, 150],
        ];
    }

    public function test_undocumented_card_number_returns_422_problem_details(): void
    {
        Queue::fake([DeliverPaymentWebhook::class]);

        $response = $this->postJson('/api/v1/payments', [
            ...$this->validPayload(),
            'payment_test_number' => '4111111111111111',
        ], ['Idempotency-Key' => 'payment-attempt-0004']);

        $response
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'VALIDATION_ERROR');
        Queue::assertNothingPushed();
    }

    public function test_missing_idempotency_key_and_unexpected_input_return_422(): void
    {
        Queue::fake([DeliverPaymentWebhook::class]);

        $response = $this->postJson('/api/v1/payments', [
            ...$this->validPayload(),
            'card_security_code' => '123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key', 'request']);
        Queue::assertNothingPushed();
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'commerce_payment_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'commerce_order_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            'amount' => '19.99',
            'currency' => 'USD',
            'callback_url' => 'https://commerce.test/api/v1/payments/webhooks/mock',
            'callback_reference' => 'ORD-100001',
            'payment_test_number' => '4000000000010001',
        ];
    }
}
