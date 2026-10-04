<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
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
            'status' => ReservationStatus::Active,
            'expires_at' => now()->addMinutes(2),
        ];
    }
}
