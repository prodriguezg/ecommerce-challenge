<?php

namespace Database\Factories;

use App\Enums\ManualReviewStatus;
use App\Models\ManualReview;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualReview>
 */
class ManualReviewFactory extends Factory
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
            'reason_code' => 'late_success_insufficient_stock',
            'status' => ManualReviewStatus::Pending,
        ];
    }
}
