<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingType;
use App\Models\ApplicationSetting;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CheckoutOrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_checkout_snapshots_totals_reserves_every_line_and_returns_token_only_once(): void
    {
        [$first, $shipping] = $this->catalog('10.0050', '10.0000', 5);
        $second = Product::factory()
            ->for($first->currency)
            ->for($first->tax)
            ->for($first->category)
            ->create(['price' => '0.0500', 'name' => 'Tiny item', 'sku' => 'TINY-1']);
        Inventory::factory()->for($second)->create(['stock_on_hand' => 2]);
        Http::fake([$this->providerUrl() => Http::response([
            'provider_payment_id' => (string) Str::ulid(),
            'status' => 'processing',
        ], 202)]);
        $payload = $this->payload($shipping, [
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 1],
        ]);

        $this->postJson('/api/v1/cart/quote', [
            'lines' => $payload['lines'],
            'shipping_method_id' => $shipping->id,
        ])->assertOk()
            ->assertJsonPath('subtotal', '20.06')
            ->assertJsonPath('product_tax', '2.01')
            ->assertJsonPath('shipping_tax', '0.50')
            ->assertJsonPath('tax', '2.51')
            ->assertJsonPath('total', '27.57');

        $response = $this->withHeader('Idempotency-Key', 'guest-checkout-key-0001')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertAccepted()
            ->assertJsonPath('guest', true)
            ->assertJsonPath('order.total', '27.57')
            ->assertJsonPath('order.currency', 'USD');
        $guestUrl = (string) $response->json('guest_order_url');
        parse_str((string) parse_url($guestUrl, PHP_URL_QUERY), $query);
        $token = (string) $query['guest_token'];
        $orderId = (string) $response->json('order.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'subtotal' => '20.0600',
            'product_tax' => '2.0100',
            'shipping_amount' => '5.0000',
            'shipping_tax' => '0.5000',
            'grand_total' => '27.5700',
            'guest_token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('orders', ['guest_token_hash' => $token]);
        $this->assertDatabaseCount('order_lines', 2);
        $this->assertDatabaseCount('reservation_items', 2);
        $this->assertDatabaseHas('reservations', ['order_id' => $orderId, 'status' => ReservationStatus::Active->value]);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'status' => PaymentStatus::Processing->value]);

        $this->withHeader('Idempotency-Key', 'guest-checkout-key-0001')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertAccepted()
            ->assertJsonMissingPath('guest_order_url')
            ->assertJsonPath('order.id', $orderId);
        $this->assertDatabaseCount('orders', 1);
        Http::assertSentCount(1);
    }

    #[TestWith([false, true, '0.00', '1.50', '50.99'])]
    #[TestWith([true, false, '3.45', '0.00', '52.94'])]
    #[TestWith([true, true, '3.45', '1.50', '54.44'])]
    #[TestWith([false, false, '0.00', '0.00', '49.49'])]
    public function test_quote_tax_breakdown_matches_persisted_order(
        bool $taxProducts,
        bool $taxShipping,
        string $productTax,
        string $shippingTax,
        string $total,
    ): void {
        [$first, $shipping] = $this->catalog('10.0000');
        $first->update(['tax_id' => $taxProducts ? $first->tax_id : null]);
        $second = Product::factory()->for($first->currency)->for($first->category)->create([
            'price' => '24.4900',
            'tax_id' => $first->tax_id,
        ]);
        Inventory::factory()->for($second)->create(['stock_on_hand' => 2]);
        $shipping->update(['amount' => '15.0000', 'tax_id' => $taxShipping ? $shipping->tax_id : null]);
        $lines = [
            ['product_id' => $first->id, 'quantity' => 1],
            ['product_id' => $second->id, 'quantity' => 1],
        ];
        Http::preventStrayRequests();
        Http::fake([$this->providerUrl() => Http::response([
            'provider_payment_id' => (string) Str::ulid(),
            'status' => 'processing',
        ], 202)]);

        $quote = $this->postJson('/api/v1/cart/quote', [
            'lines' => $lines,
            'shipping_method_id' => $shipping->id,
        ])->assertOk()
            ->assertJsonPath('subtotal', '34.49')
            ->assertJsonPath('product_tax', $productTax)
            ->assertJsonPath('shipping', '15.00')
            ->assertJsonPath('shipping_tax', $shippingTax)
            ->assertJsonPath('total', $total);
        $this->assertSame((string) BigDecimal::of($productTax)->plus($shippingTax), $quote->json('tax'));
        $this->assertSame((string) BigDecimal::of('34.49')->plus($productTax)->plus('15.00')->plus($shippingTax), $quote->json('total'));

        $checkout = $this->withHeader('Idempotency-Key', 'tax-breakdown-checkout-01')
            ->postJson('/api/v1/checkouts', $this->payload($shipping, $lines))
            ->assertAccepted()
            ->assertJsonPath('order.total', $total);
        $this->assertDatabaseHas('orders', [
            'id' => $checkout->json('order.id'),
            'subtotal' => '34.4900',
            'product_tax' => (string) BigDecimal::of($productTax)->toScale(4),
            'shipping_amount' => '15.0000',
            'shipping_tax' => (string) BigDecimal::of($shippingTax)->toScale(4),
            'grand_total' => (string) BigDecimal::of($total)->toScale(4),
        ]);
        Http::assertSentCount(1);
    }

    public function test_conflicting_idempotency_payload_is_rejected(): void
    {
        [$product, $shipping] = $this->catalog();
        Http::fake([$this->providerUrl() => Http::response(['provider_payment_id' => (string) Str::ulid(), 'status' => 'processing'], 202)]);
        $payload = $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]);

        $this->withHeader('Idempotency-Key', 'conflict-key-00000001')->postJson('/api/v1/checkouts', $payload)->assertAccepted();
        $payload['lines'][0]['quantity'] = 2;
        $this->withHeader('Idempotency-Key', 'conflict-key-00000001')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_is_all_or_nothing_when_any_line_lacks_available_stock(): void
    {
        [$available, $shipping] = $this->catalog(stock: 5);
        $unavailable = Product::factory()->for($available->currency)->for($available->tax)->for($available->category)->create();
        Inventory::factory()->for($unavailable)->create(['stock_on_hand' => 0]);

        $this->withHeader('Idempotency-Key', 'atomic-stock-key-0001')
            ->postJson('/api/v1/checkouts', $this->payload($shipping, [
                ['product_id' => $available->id, 'quantity' => 1],
                ['product_id' => $unavailable->id, 'quantity' => 1],
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'inventory_unavailable');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('checkout_idempotencies', 0);
        Http::assertNothingSent();
    }

    public function test_an_active_reservation_prevents_the_last_unit_from_being_oversold(): void
    {
        [$product, $shipping] = $this->catalog(stock: 1);
        Http::fake([$this->providerUrl() => Http::response(['provider_payment_id' => (string) Str::ulid(), 'status' => 'processing'], 202)]);
        $payload = $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]);

        $this->withHeader('Idempotency-Key', 'first-buyer-key-00001')->postJson('/api/v1/checkouts', $payload)->assertAccepted();
        $payload['email'] = 'second@example.test';
        $this->withHeader('Idempotency-Key', 'second-buyer-key-0001')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'inventory_unavailable');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('reservation_items', 1);
    }

    public function test_database_timeout_override_is_snapshotted_on_new_reservation(): void
    {
        [$product, $shipping] = $this->catalog();
        ApplicationSetting::factory()->create([
            'key' => 'reservation_timeout_seconds',
            'value' => '300',
            'type' => SettingType::Integer,
        ]);
        Http::fake([$this->providerUrl() => Http::response(['provider_payment_id' => (string) Str::ulid(), 'status' => 'processing'], 202)]);
        $before = now();

        $this->withHeader('Idempotency-Key', 'timeout-override-key-1')
            ->postJson('/api/v1/checkouts', $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]))
            ->assertAccepted();

        $expiresAt = now()->parse((string) \DB::table('reservations')->value('expires_at'));
        $this->assertGreaterThanOrEqual(299, $before->diffInSeconds($expiresAt));
        $this->assertLessThanOrEqual(301, $before->diffInSeconds($expiresAt));
    }

    public function test_payment_initiation_failure_releases_reservation_and_is_idempotent(): void
    {
        [$product, $shipping] = $this->catalog();
        Http::fakeSequence()->pushFailedConnection('connection failed')->pushFailedConnection('connection failed');
        $payload = $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]);

        $this->withHeader('Idempotency-Key', 'provider-failure-key-1')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertStatus(500)
            ->assertJsonPath('code', 'payment_initiation_failed');

        $this->assertDatabaseHas('orders', ['status' => OrderStatus::PaymentFailed->value]);
        $this->assertDatabaseHas('reservations', ['status' => ReservationStatus::Released->value]);
        $this->assertDatabaseHas('payments', ['status' => PaymentStatus::InitiationFailed->value]);
        Http::assertSentCount(2);

        $this->withHeader('Idempotency-Key', 'provider-failure-key-1')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertStatus(500);
        $this->assertDatabaseCount('orders', 1);
        Http::assertSentCount(2);
    }

    public function test_customer_and_guest_order_access_enforce_ownership_token_and_expiry(): void
    {
        [$product, $shipping] = $this->catalog(stock: 3);
        Http::fake(fn () => Http::response(['provider_payment_id' => (string) Str::ulid(), 'status' => 'processing'], 202));
        $customer = User::factory()->create(['email' => 'customer@example.test']);
        $other = User::factory()->create();
        $payload = $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]);
        $payload['email'] = $customer->email;

        $customerOrder = $this->actingAs($customer)->withHeader('Idempotency-Key', 'customer-order-key-01')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertAccepted()
            ->json('order.id');
        $this->actingAs($customer)->getJson("/api/v1/orders/{$customerOrder}")->assertOk();
        $this->actingAs($customer)->getJson("/api/v1/orders/{$customerOrder}/status")->assertOk()->assertJsonPath('payment_status', 'pending');
        $this->actingAs($other)->getJson("/api/v1/orders/{$customerOrder}")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $payload['email'] = 'guest-access@example.test';
        $guest = $this->withHeader('Idempotency-Key', 'guest-access-key-0001')->postJson('/api/v1/checkouts', $payload)->assertAccepted();
        $url = (string) $guest->json('guest_order_url');
        $this->getJson($url)->assertOk()->assertJsonPath('id', $guest->json('order.id'));
        $this->getJson(str_replace('guest_token=', 'guest_token=wrong', $url))->assertNotFound();

        Order::query()->findOrFail($guest->json('order.id'))->forceFill(['guest_token_expires_at' => now()->subSecond()])->save();
        $this->getJson($url)->assertNotFound();
    }

    public function test_guest_must_login_when_email_is_registered_and_admin_cannot_checkout(): void
    {
        [$product, $shipping] = $this->catalog();
        $customer = User::factory()->create(['email' => 'registered@example.test']);
        $payload = $this->payload($shipping, [['product_id' => $product->id, 'quantity' => 1]]);
        $payload['email'] = $customer->email;

        $this->withHeader('Idempotency-Key', 'registered-email-key1')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'login_required');

        $this->actingAs(User::factory()->admin()->create())
            ->withHeader('Idempotency-Key', 'administrator-key-001')
            ->postJson('/api/v1/checkouts', $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    /** @return array{Product, ShippingMethod} */
    private function catalog(string $price = '10.0000', string $taxRate = '10.0000', int $stock = 10): array
    {
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create(['rate' => $taxRate]);
        $category = Category::factory()->create();
        $product = Product::factory()->for($currency)->for($tax)->for($category)->create(['price' => $price]);
        Inventory::factory()->for($product)->create(['stock_on_hand' => $stock]);
        $shipping = ShippingMethod::factory()->for($currency)->for($tax)->create(['amount' => '5.0000']);

        return [$product, $shipping];
    }

    /** @param list<array{product_id: string, quantity: int}> $lines */
    private function payload(ShippingMethod $shipping, array $lines): array
    {
        return [
            'email' => 'guest@example.test',
            'shipping_address' => [
                'name' => 'Guest Buyer',
                'line1' => '1 Main Street',
                'line2' => null,
                'city' => 'Montevideo',
                'region' => 'Montevideo',
                'postal_code' => '11000',
                'country' => 'UY',
                'phone' => '+598 1 234 567',
            ],
            'shipping_method_id' => $shipping->id,
            'lines' => $lines,
            'payment_test_number' => '4000000000010001',
        ];
    }

    private function providerUrl(): string
    {
        return (string) config('api.checkout.payment_url');
    }
}
