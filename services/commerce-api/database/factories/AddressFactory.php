<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'line_1' => fake()->streetAddress(),
            'line_2' => null,
            'city' => fake()->city(),
            'region' => 'California',
            'postal_code' => fake()->postcode(),
            'country_code' => 'US',
            'phone' => fake()->phoneNumber(),
            'is_default' => true,
        ];
    }
}
