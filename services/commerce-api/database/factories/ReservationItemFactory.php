<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 */
class ReservationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'inventory_id' => Inventory::factory(),
            'product_id' => fn (array $attributes): string => Inventory::findOrFail($attributes['inventory_id'])->product_id,
            'quantity' => 1,
        ];
    }
}
