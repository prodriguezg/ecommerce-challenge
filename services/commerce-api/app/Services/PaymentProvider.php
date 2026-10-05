<?php

namespace App\Services;

use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymentProvider
{
    /** @return array{provider_payment_id: string, status: string} */
    public function create(Payment $payment, string $testNumber): array
    {
        $payload = [
            'commerce_payment_id' => $payment->id,
            'commerce_order_id' => $payment->order_id,
            'amount' => (string) BigDecimal::of((string) $payment->amount)->toScale(2, RoundingMode::HalfUp),
            'currency' => $payment->currency_code,
            'callback_url' => (string) config('api.checkout.payment_callback_url'),
            'callback_reference' => $payment->id,
            'payment_test_number' => $testNumber,
        ];
        $lastException = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout((int) config('api.checkout.payment_connect_timeout_seconds'))
                    ->timeout((int) config('api.checkout.payment_timeout_seconds'))
                    ->withHeader('Idempotency-Key', $payment->idempotency_key)
                    ->post((string) config('api.checkout.payment_url'), $payload);

                if ($response->status() !== 202) {
                    throw new RuntimeException('The payment provider rejected initiation.');
                }

                /** @var array{provider_payment_id: string, status: string} $acceptance */
                $acceptance = $response->json();

                return $acceptance;
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }
        }

        throw new RuntimeException('The payment provider could not be reached after retry.', previous: $lastException);
    }
}
