<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_live_endpoint_returns_service_status(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'payment-api',
                'status' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_service_status_when_redis_is_available(): void
    {
        Redis::shouldReceive('connection->command')
            ->once()
            ->with('ping')
            ->andReturn('PONG');

        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'payment-api',
                'status' => 'ready',
            ]);
    }

    public function test_ready_endpoint_returns_503_when_redis_is_unavailable(): void
    {
        Redis::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('unavailable'));

        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'service' => 'payment-api',
                'status' => 'unavailable',
            ]);
    }
}
