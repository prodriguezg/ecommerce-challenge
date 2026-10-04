<?php

namespace Database\Factories;

use App\Enums\ImportMode;
use App\Enums\UnknownCategoryPolicy;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory()->admin(),
            'original_filename' => 'products.csv',
            'mode' => ImportMode::CreateOnly,
            'unknown_category_policy' => UnknownCategoryPolicy::Reject,
            'stock_override' => false,
            'stock_override_confirmed_at' => null,
            'total_count' => 0,
            'imported_count' => 0,
            'warning_count' => 0,
            'rejected_count' => 0,
        ];
    }
}
