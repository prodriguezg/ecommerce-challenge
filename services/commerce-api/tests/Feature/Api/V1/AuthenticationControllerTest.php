<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.url'));
    }

    public function test_valid_case_insensitive_credentials_regenerate_session_and_return_principal(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password_hash' => 'SecurePass1!',
        ]);
        $this->withSession(['marker' => true]);
        $previousSessionId = session()->getId();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => ' CUSTOMER@EXAMPLE.COM ',
            'password' => 'SecurePass1!',
        ]);

        $response->assertOk()->assertExactJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => 'customer@example.com',
            'role' => 'customer',
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_invalid_credentials_return_the_same_401_problem_for_existing_and_unknown_emails(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password_hash' => 'SecurePass1!',
        ]);

        $existing = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'WrongPass1!',
        ]);
        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'WrongPass1!',
        ]);

        $existing->assertUnauthorized()->assertHeader('content-type', 'application/problem+json');
        $unknown->assertUnauthorized()->assertHeader('content-type', 'application/problem+json');
        $this->assertSame($existing->json('detail'), $unknown->json('detail'));
        $this->assertGuest();
    }

    public function test_authenticated_principal_can_read_identity_then_logout_and_invalidate_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('role', 'customer');

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertFalse(session()->has(Auth::guard('web')->getName()));
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_login_rate_limit_returns_problem_details_with_429(): void
    {
        config()->set('authentication.rate_limits.login', 1);

        $payload = ['email' => 'limited@example.com', 'password' => 'WrongPass1!'];
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'rate_limit_exceeded');
    }

    public function test_state_changing_spa_request_without_csrf_proof_returns_403_problem_details(): void
    {
        app()->instance('env', 'local');

        try {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'customer@example.com',
                'password' => 'SecurePass1!',
            ])
                ->assertForbidden()
                ->assertHeader('content-type', 'application/problem+json')
                ->assertJsonPath('code', 'csrf_token_mismatch');
        } finally {
            app()->instance('env', 'testing');
        }
    }
}
