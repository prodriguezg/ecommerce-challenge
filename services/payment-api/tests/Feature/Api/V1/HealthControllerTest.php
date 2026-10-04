<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_live_endpoint_returns_service_status(): void
    {
        $response = $this->getJson('/health/live');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'payment-api',
                'status' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_service_status_when_redis_is_available(): void
    {
        Cache::put('payment-worker-heartbeat', now()->toIso8601String(), 10);
        Redis::shouldReceive('connection->command')
            ->once()
            ->with('ping')
            ->andReturn('PONG');

        $response = $this->getJson('/health/ready');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'payment-api',
                'status' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_503_when_worker_heartbeat_is_missing(): void
    {
        Redis::shouldReceive('connection->command')
            ->once()
            ->with('ping')
            ->andReturn('PONG');

        $response = $this->getJson('/health/ready');

        $response
            ->assertServiceUnavailable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'PAYMENT_SERVICE_UNAVAILABLE');
    }

    public function test_ready_endpoint_returns_503_when_redis_is_unavailable(): void
    {
        Redis::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('unavailable'));

        $response = $this->getJson('/health/ready');

        $response
            ->assertServiceUnavailable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'PAYMENT_SERVICE_UNAVAILABLE');
    }
}
