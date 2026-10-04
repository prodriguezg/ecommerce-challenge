<?php

namespace Database\Factories;

use App\Enums\InventoryAdjustmentReason;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAdjustment>
 */
class InventoryAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'product_id' => fn (array $attributes): string => Inventory::findOrFail($attributes['inventory_id'])->product_id,
            'previous_quantity' => 5,
            'new_quantity' => 10,
            'delta' => 5,
            'reason' => InventoryAdjustmentReason::StockReceived,
            'actor_user_id' => User::factory()->admin(),
        ];
    }
}
