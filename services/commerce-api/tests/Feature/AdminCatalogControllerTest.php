<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->currency = Currency::factory()->usd()->create();
        $this->actingAs($this->admin);
    }

    public function test_admin_can_create_a_trimmed_product_with_inventory_and_audit_event(): void
    {
        $response = $this->postJson('/api/v1/admin/products', $this->productPayload());

        $response->assertCreated()->assertJsonPath('sku', 'Demo-1')->assertJsonPath('name', 'Demo Product');
        $product = Product::firstOrFail();
        $this->assertSame('DEMO-1', $product->normalized_sku);
        $this->assertSame(7, $product->inventory?->stock_on_hand);
        $this->assertDatabaseHas('product_sku_reservations', ['normalized_sku' => 'DEMO-1']);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->getKey(), 'new_quantity' => 7]);
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'product.created', 'actor_user_id' => $this->admin->getKey()]);
    }

    public function test_customer_cannot_use_admin_catalog(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/products', $this->productPayload())->assertForbidden();
    }

    public function test_stale_product_update_does_not_modify_the_product(): void
    {
        $product = $this->createProduct();
        $payload = $this->productPayload(['name' => 'Changed']);

        $this->withHeader('If-Match-Version', '99')->putJson("/api/v1/admin/products/{$product->getKey()}", $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'stale_version');

        $this->assertSame('Demo Product', $product->fresh()->name);
        $this->assertSame(1, $product->fresh()->version);
    }

    public function test_hard_deletion_keeps_the_sku_permanently_reserved(): void
    {
        $product = $this->createProduct();

        $this->withHeader('If-Match-Version', '1')->deleteJson("/api/v1/admin/products/{$product->getKey()}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);
        $this->assertDatabaseHas('product_sku_reservations', ['normalized_sku' => 'DEMO-1', 'product_id' => null]);

        $this->postJson('/api/v1/admin/products', $this->productPayload())->assertConflict()->assertJsonPath('code', 'product_conflict');
    }

    public function test_reservation_history_forces_product_soft_deletion(): void
    {
        $product = $this->createProduct();
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Released]);
        ReservationItem::factory()->create([
            'reservation_id' => $reservation->getKey(),
            'product_id' => $product->getKey(),
            'inventory_id' => $product->inventory->getKey(),
        ]);

        $this->withHeader('If-Match-Version', '1')->deleteJson("/api/v1/admin/products/{$product->getKey()}")->assertNoContent();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('products', ['id' => $product->getKey(), 'normalized_sku' => 'DEMO-1']);
    }

    public function test_category_deletion_clears_product_references(): void
    {
        $category = Category::factory()->create();
        $product = $this->createProduct(['category_id' => $category->getKey()]);

        $this->withHeader('If-Match-Version', '1')->deleteJson("/api/v1/admin/categories/{$category->getKey()}")->assertNoContent();

        $this->assertSoftDeleted($category);
        $this->assertNull($product->fresh()->category_id);
        $this->assertSame(2, $product->fresh()->version);
    }

    public function test_tax_deletion_reports_active_reference_counts(): void
    {
        $tax = Tax::factory()->create();
        $this->createProduct(['tax_id' => $tax->getKey()]);
        ShippingMethod::factory()->create(['tax_id' => $tax->getKey(), 'currency_id' => $this->currency->getKey()]);

        $this->withHeader('If-Match-Version', '1')->deleteJson("/api/v1/admin/taxes/{$tax->getKey()}")
            ->assertConflict()
            ->assertJsonPath('active_product_references', 1)
            ->assertJsonPath('active_shipping_method_references', 1);
    }

    public function test_shipping_deletion_clears_active_cart_selection(): void
    {
        $method = ShippingMethod::factory()->create(['currency_id' => $this->currency->getKey()]);
        $cart = Cart::factory()->create(['currency_id' => $this->currency->getKey(), 'shipping_method_id' => $method->getKey()]);

        $this->withHeader('If-Match-Version', '1')->deleteJson("/api/v1/admin/shipping-methods/{$method->getKey()}")->assertNoContent();

        $this->assertNull($cart->fresh()->shipping_method_id);
        $this->assertSame(2, $cart->fresh()->version);
    }

    /** @param array<string, mixed> $overrides */
    private function createProduct(array $overrides = []): Product
    {
        $response = $this->postJson('/api/v1/admin/products', $this->productPayload($overrides))->assertCreated();

        return Product::findOrFail($response->json('id'));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function productPayload(array $overrides = []): array
    {
        return array_replace([
            'sku' => '  Demo-1  ',
            'name' => '  Demo Product  ',
            'description' => 'Description',
            'price' => '19.99',
            'currency' => 'USD',
            'category_id' => null,
            'tax_id' => null,
            'active' => true,
            'initial_on_hand' => 7,
        ], $overrides);
    }
}
