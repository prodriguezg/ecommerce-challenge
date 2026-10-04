<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'tax_id' => Tax::factory(),
            'price' => number_format(fake()->randomFloat(2, 1, 500), 4, '.', ''),
            'currency_id' => Currency::factory(),
            'weight_kg' => number_format(fake()->randomFloat(3, 0, 25), 4, '.', ''),
            'version' => 1,
        ];
    }
}
