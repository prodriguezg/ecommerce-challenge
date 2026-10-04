<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->unique()->word(),
            'symbol' => '$',
            'rate_to_base' => '1.000000000000',
            'is_base' => false,
            'minor_units' => 2,
            'rate_updated_at' => now(),
        ];
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate_to_base' => '1.000000000000',
            'is_base' => true,
            'minor_units' => 2,
        ]);
    }
}
