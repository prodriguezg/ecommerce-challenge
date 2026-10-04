<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sequence_number' => fake()->unique()->numberBetween(100000, 999999),
            'order_code' => fake()->unique()->numerify('ORD-######'),
            'customer_user_id' => null,
            'email' => fake()->safeEmail(),
            'normalized_email' => fn (array $attributes): string => strtolower($attributes['email']),
            'currency_id' => Currency::factory(),
            'currency_code' => 'USD',
            'status' => OrderStatus::AwaitingPayment,
            'subtotal' => '10.0000',
            'product_tax' => '1.0000',
            'shipping_amount' => '5.0000',
            'shipping_tax' => '0.5000',
            'grand_total' => '16.5000',
            'shipping_method_name' => 'Ground',
            'version' => 1,
        ];
    }
}
