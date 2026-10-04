<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'provider_payment_id' => null,
            'status' => PaymentStatus::Requested,
            'amount' => '16.5000',
            'currency_id' => Currency::factory(),
            'currency_code' => 'USD',
        ];
    }
}
