<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GuestOrderRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.url'));
    }

    public function test_paid_guest_order_creates_customer_copies_default_address_and_revokes_token_with_201(): void
    {
        $token = str_repeat('a', 64);
        $order = $this->guestOrder($token, OrderStatus::Paid);

        $response = $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", [
            'password' => 'SecurePass1!',
        ]);

        $customer = User::query()->sole();
        $response->assertCreated()->assertExactJson([
            'id' => $customer->id,
            'name' => 'Guest Buyer',
            'email' => 'guest@example.test',
            'role' => 'customer',
            'default_address' => [
                'name' => 'Guest Buyer',
                'line1' => '1 Main Street',
                'line2' => 'Unit 2',
                'city' => 'Montevideo',
                'region' => 'Montevideo',
                'postal_code' => '11000',
                'country' => 'UY',
                'phone' => '+598 1 234 567',
            ],
        ]);
        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->id,
            'recipient_name' => 'Guest Buyer',
            'line_1' => '1 Main Street',
            'line_2' => 'Unit 2',
            'city' => 'Montevideo',
            'region' => 'Montevideo',
            'postal_code' => '11000',
            'country_code' => 'UY',
            'phone' => '+598 1 234 567',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_user_id' => $customer->id,
            'guest_token_hash' => null,
            'guest_token_expires_at' => null,
            'version' => 2,
        ]);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/guest-orders/{$order->id}?guest_token={$token}")->assertNotFound();
    }

    public function test_returns_409_when_guest_order_has_not_completed_successfully(): void
    {
        $token = str_repeat('b', 64);
        $order = $this->guestOrder($token, OrderStatus::AwaitingPayment);

        $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", [
            'password' => 'SecurePass1!',
        ])
            ->assertConflict()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'order_not_eligible');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_user_id' => null,
            'guest_token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_returns_404_for_wrong_expired_or_revoked_guest_tokens(): void
    {
        $validToken = str_repeat('c', 64);
        $wrongToken = str_repeat('d', 64);
        $wrong = $this->guestOrder($validToken, OrderStatus::Paid);
        $expiredToken = str_repeat('e', 64);
        $expired = $this->guestOrder($expiredToken, OrderStatus::Paid, now()->subSecond());
        $revokedToken = str_repeat('f', 64);
        $revoked = $this->guestOrder($revokedToken, OrderStatus::Paid);
        $revoked->forceFill(['guest_token_hash' => null, 'guest_token_expires_at' => null])->save();

        $this->postJson("/api/v1/guest-orders/{$wrong->id}/register?guest_token={$wrongToken}", $this->passwordPayload())
            ->assertNotFound();
        $this->postJson("/api/v1/guest-orders/{$expired->id}/register?guest_token={$expiredToken}", $this->passwordPayload())
            ->assertNotFound();
        $this->postJson("/api/v1/guest-orders/{$revoked->id}/register?guest_token={$revokedToken}", $this->passwordPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_existing_customer_email_returns_login_required_without_claiming_order(): void
    {
        User::factory()->create(['email' => 'GUEST@example.test']);
        $token = str_repeat('1', 64);
        $order = $this->guestOrder($token, OrderStatus::Paid);

        $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", $this->passwordPayload())
            ->assertConflict()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'login_required');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_user_id' => null,
            'guest_token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_second_claim_cannot_create_another_account_or_address(): void
    {
        $token = str_repeat('2', 64);
        $order = $this->guestOrder($token, OrderStatus::Paid);

        $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", $this->passwordPayload())
            ->assertCreated();
        $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", $this->passwordPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_invalid_password_and_unexpected_input_return_422_without_claiming_order(): void
    {
        $token = str_repeat('3', 64);
        $order = $this->guestOrder($token, OrderStatus::Paid);

        $this->postJson("/api/v1/guest-orders/{$order->id}/register?guest_token={$token}", [
            'password' => 'weak',
            'email' => 'replacement@example.test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'email']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_user_id' => null,
            'guest_token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_guest_order_access_rate_limit_returns_problem_details_with_429(): void
    {
        config()->set('api.checkout.guest_order_rate_limit', 1);
        $token = str_repeat('4', 64);
        $order = $this->guestOrder($token, OrderStatus::Paid);

        $this->getJson("/api/v1/guest-orders/{$order->id}?guest_token={$token}")->assertOk();

        $this->getJson("/api/v1/guest-orders/{$order->id}/status?guest_token={$token}")
            ->assertTooManyRequests()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'rate_limit_exceeded');
    }

    private function guestOrder(string $token, OrderStatus $status, mixed $expiresAt = null): Order
    {
        $order = Order::factory()->create([
            'email' => 'guest@example.test',
            'normalized_email' => 'guest@example.test',
            'status' => $status,
            'guest_token_hash' => hash('sha256', $token),
            'guest_token_expires_at' => $expiresAt ?? now()->addDay(),
        ]);
        OrderAddress::factory()->for($order)->create([
            'recipient_name' => 'Guest Buyer',
            'line_1' => '1 Main Street',
            'line_2' => 'Unit 2',
            'city' => 'Montevideo',
            'region' => 'Montevideo',
            'postal_code' => '11000',
            'country_code' => 'UY',
            'phone' => '+598 1 234 567',
            'email' => 'guest@example.test',
        ]);

        return $order;
    }

    /** @return array{password: string} */
    private function passwordPayload(): array
    {
        return ['password' => 'SecurePass1!'];
    }
}
