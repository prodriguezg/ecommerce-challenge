<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAddress>
 */
class OrderAddressFactory extends Factory
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
            'recipient_name' => fake()->name(),
            'line_1' => fake()->streetAddress(),
            'line_2' => null,
            'city' => fake()->city(),
            'region' => 'California',
            'postal_code' => fake()->postcode(),
            'country_code' => 'US',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
        ];
    }
}
