<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.url'));
    }

    public function test_status_reports_setup_availability(): void
    {
        $this->getJson('/api/v1/setup/status')->assertOk()->assertExactJson(['available' => true]);

        User::factory()->admin()->create();

        $this->getJson('/api/v1/setup/status')->assertOk()->assertExactJson(['available' => false]);
    }

    public function test_valid_payload_creates_and_authenticates_the_sole_administrator_with_201(): void
    {
        $response = $this->postJson('/api/v1/setup/admin', [
            'name' => '  Store Administrator  ',
            'email' => '  ADMIN@Example.com ',
            'password' => 'SecurePass1!',
        ]);

        $response->assertCreated()->assertExactJson([
            'id' => User::query()->sole()->id,
            'name' => 'Store Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $administrator = User::query()->sole();
        $this->assertSame(UserRole::Admin, $administrator->role);
        $this->assertTrue(Hash::check('SecurePass1!', $administrator->password_hash));
        $this->assertAuthenticatedAs($administrator);
    }

    public function test_second_setup_returns_problem_details_with_409_and_does_not_create_another_admin(): void
    {
        User::factory()->admin()->create();

        $response = $this->postJson('/api/v1/setup/admin', [
            'name' => 'Another Administrator',
            'email' => 'another@example.com',
            'password' => 'SecurePass1!',
        ]);

        $response
            ->assertConflict()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'setup_unavailable');
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{string}> */
    public static function invalidPasswords(): array
    {
        return [
            'fewer than ten characters' => ['Short1!'],
            'missing uppercase' => ['lowercase1!'],
            'missing number' => ['NoNumber!!'],
            'missing symbol' => ['NoSymbols12'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_returns_problem_details_with_422(string $password): void
    {
        $response = $this->postJson('/api/v1/setup/admin', [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => $password,
        ]);

        $response
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_setup_rejects_an_unexpected_field_with_422(): void
    {
        $response = $this->postJson('/api/v1/setup/admin', [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => 'SecurePass1!',
            'role' => 'customer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 0);
    }
}
