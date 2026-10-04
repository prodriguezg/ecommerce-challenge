<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'action_code' => 'product.updated',
            'target_type' => 'product',
            'target_id' => (string) Str::ulid(),
            'before_data' => [],
            'after_data' => [],
            'request_context' => [],
        ];
    }
}
