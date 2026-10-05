<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_payment_limit_is_configurable_and_returns_a_safe_429_problem(): void
    {
        config()->set('payment.request_rate_limit', 1);

        $this->postJson('/api/v1/payments', [])->assertUnprocessable();

        $this->postJson('/api/v1/payments', [])
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'rate_limit_exceeded');
    }

    public function test_missing_route_returns_problem_details_without_query_secrets(): void
    {
        $response = $this->getJson('/api/v1/not-found?token=private-token');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('instance', '/api/v1/not-found')
            ->assertJsonPath('code', 'not_found');
        $this->assertStringNotContainsString('private-token', $response->getContent());
    }
}
