<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
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
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'quantity' => 1,
            'weight_kg' => '1.0000',
            'unit_price' => '10.0000',
            'line_subtotal' => '10.0000',
            'tax_name' => 'Standard',
            'tax_rate' => '10.0000',
            'tax_amount' => '1.0000',
            'line_total' => '11.0000',
            'currency_id' => Currency::factory(),
            'currency_code' => 'USD',
        ];
    }
}
