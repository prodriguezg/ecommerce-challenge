<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentStateHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentStateHistory>
 */
class PaymentStateHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'from_status' => null,
            'to_status' => PaymentStatus::Requested,
            'metadata' => [],
        ];
    }
}
