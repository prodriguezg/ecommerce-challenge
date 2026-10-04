<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\ApplicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationSetting>
 */
class ApplicationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'value' => '120',
            'type' => SettingType::Integer,
            'description' => fake()->sentence(),
            'version' => 1,
        ];
    }
}
