<?php

namespace App\Actions;

use App\Enums\PaymentOutcome;
use App\Exceptions\IdempotencyConflictException;
use App\Jobs\DeliverPaymentWebhook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class AcceptPayment
{
    /**
     * @param  array<string, string>  $payment
     * @return array{provider_payment_id: string, status: string}
     */
    public function handle(string $idempotencyKey, array $payment): array
    {
        $cacheKey = 'payment-idempotency:'.hash('sha256', $idempotencyKey);
        $payloadHash = hash('sha256', json_encode($payment, JSON_THROW_ON_ERROR));

        return Cache::lock($cacheKey.':lock', 10)->block(5, function () use ($cacheKey, $payloadHash, $payment): array {
            /** @var array{payload_hash: string, acceptance: array{provider_payment_id: string, status: string}}|null $existing */
            $existing = Cache::get($cacheKey);

            if ($existing !== null) {
                if (! hash_equals($existing['payload_hash'], $payloadHash)) {
                    throw new IdempotencyConflictException;
                }

                return $existing['acceptance'];
            }

            $acceptance = [
                'provider_payment_id' => (string) Str::ulid(),
                'status' => 'processing',
            ];
            $outcome = PaymentOutcome::fromTestNumber($payment['payment_test_number']);
            $delay = $this->delayFor($payment['payment_test_number']);

            Cache::forever($cacheKey, [
                'payload_hash' => $payloadHash,
                'acceptance' => $acceptance,
            ]);

            try {
                DeliverPaymentWebhook::dispatch(
                    eventId: (string) Str::ulid(),
                    providerPaymentId: $acceptance['provider_payment_id'],
                    outcome: $outcome,
                    callbackUrl: $payment['callback_url'],
                    occurredAt: now()->addSeconds($delay)->utc()->format('Y-m-d\TH:i:s\Z'),
                )->delay(now()->addSeconds($delay));
            } catch (Throwable $exception) {
                Cache::forget($cacheKey);

                throw $exception;
            }

            return $acceptance;
        });
    }

    private function delayFor(string $testNumber): int
    {
        if ($testNumber === '4000000000080004') {
            return (int) config('payment.late_success_delay_seconds');
        }

        return random_int(
            (int) config('payment.normal_delay_min_seconds'),
            (int) config('payment.normal_delay_max_seconds'),
        );
    }
}
