<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.url'));
    }

    public function test_valid_payload_creates_customer_and_default_address_atomically_with_201(): void
    {
        $response = $this->postJson('/api/v1/customers/register', $this->validPayload());

        $customer = User::query()->sole();
        $response->assertCreated()->assertExactJson([
            'id' => $customer->id,
            'name' => 'Customer One',
            'email' => 'customer@example.com',
            'role' => 'customer',
            'default_address' => [
                'name' => 'Customer One',
                'line1' => '123 Example Street',
                'line2' => 'Apartment 4',
                'city' => 'Montevideo',
                'region' => 'Montevideo',
                'postal_code' => '11000',
                'country' => 'UY',
                'phone' => '+598 91 234 567',
            ],
        ]);
        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->id,
            'recipient_name' => 'Customer One',
            'line_1' => '123 Example Street',
            'line_2' => 'Apartment 4',
            'city' => 'Montevideo',
            'region' => 'Montevideo',
            'postal_code' => '11000',
            'country_code' => 'UY',
            'phone' => '+598 91 234 567',
            'is_default' => true,
        ]);
    }

    public function test_case_insensitive_duplicate_email_returns_422_without_creating_address(): void
    {
        User::factory()->create(['email' => 'Customer@Example.com']);
        $payload = $this->validPayload();
        $payload['email'] = ' CUSTOMER@example.COM ';

        $response = $this->postJson('/api/v1/customers/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_missing_default_address_fields_return_422_without_creating_customer(): void
    {
        $payload = $this->validPayload();
        unset($payload['address']['phone'], $payload['address']['region']);

        $response = $this->postJson('/api/v1/customers/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address.phone', 'address.region']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_unexpected_nested_address_field_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['address']['is_default'] = false;

        $this->postJson('/api/v1/customers/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address');
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'name' => 'Customer One',
            'email' => 'customer@example.com',
            'password' => 'SecurePass1!',
            'address' => [
                'name' => 'Customer One',
                'line1' => '123 Example Street',
                'line2' => 'Apartment 4',
                'city' => 'Montevideo',
                'region' => 'Montevideo',
                'postal_code' => '11000',
                'country' => 'uy',
                'phone' => '+598 91 234 567',
            ],
        ];
    }
}
