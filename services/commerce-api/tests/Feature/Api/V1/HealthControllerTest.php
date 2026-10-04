<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_live_endpoint_returns_service_status(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'commerce-api',
                'status' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_service_status_when_database_is_available(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'commerce-api',
                'status' => 'ready',
            ]);
    }
}
