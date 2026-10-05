<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_checkout_limit_is_configurable_and_returns_a_safe_429_problem(): void
    {
        config()->set('api.rate_limits.checkout', 1);

        $this->postJson('/api/v1/checkouts', [])->assertUnprocessable();

        $this->postJson('/api/v1/checkouts', [])
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'rate_limit_exceeded');
    }

    public function test_upload_limits_are_independently_configurable(): void
    {
        config()->set('api.rate_limits.image_upload', 1);
        config()->set('api.rate_limits.csv_upload', 1);
        $this->actingAs(User::factory()->admin()->create());
        $productId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $this->postJson("/api/v1/admin/products/{$productId}/image", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/products/{$productId}/image", [])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'rate_limit_exceeded');

        $this->postJson('/api/v1/admin/product-imports', [])->assertUnprocessable();
        $this->postJson('/api/v1/admin/product-imports', [])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'rate_limit_exceeded');
    }

    public function test_problem_instance_does_not_echo_sensitive_query_values(): void
    {
        $response = $this->getJson('/api/v1/not-found?guest_token=private-token');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('instance', '/api/v1/not-found')
            ->assertJsonMissing(['guest_token' => 'private-token']);
        $this->assertStringNotContainsString('private-token', $response->getContent());
    }

    public function test_cors_allows_only_the_configured_same_origin(): void
    {
        $allowedOrigin = (string) config('cors.allowed_origins.0');

        $this->withHeaders([
            'Origin' => $allowedOrigin,
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/products')->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);

        $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/products')
            ->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
    }
}
