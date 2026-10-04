<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_event_id' => (string) Str::uuid(),
            'provider_payment_id' => (string) Str::uuid(),
            'event_type' => 'payment.updated',
            'status' => 'succeeded',
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'metadata' => [],
            'received_at' => now(),
        ];
    }
}
