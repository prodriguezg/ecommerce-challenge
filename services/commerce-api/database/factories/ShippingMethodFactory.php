<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ShippingMethod;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'amount' => '5.0000',
            'currency_id' => Currency::factory(),
            'tax_id' => Tax::factory(),
            'version' => 1,
        ];
    }
}
