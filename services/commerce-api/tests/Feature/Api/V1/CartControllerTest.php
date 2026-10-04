<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cart_endpoints_require_a_customer_principal(): void
    {
        Currency::factory()->usd()->create();

        $this->getJson('/api/v1/cart')->assertUnauthorized();

        $administrator = User::factory()->admin()->create();
        $this->actingAs($administrator)
            ->getJson('/api/v1/cart')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        $this->assertDatabaseMissing('carts', ['user_id' => $administrator->id]);
    }

    public function test_customer_can_set_read_and_remove_an_authoritatively_priced_cart_item(): void
    {
        [$customer, $product] = $this->customerAndProduct('10.0050', '10.0000', 5);

        $this->actingAs($customer)
            ->putJson("/api/v1/cart/items/{$product->id}", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('lines.0.quantity', 2)
            ->assertJsonPath('lines.0.unit_price', '10.01')
            ->assertJsonPath('subtotal', '20.01')
            ->assertJsonPath('tax', '2.00')
            ->assertJsonPath('total', '22.01')
            ->assertJsonPath('requires_confirmation', false);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($customer)
            ->deleteJson("/api/v1/cart/items/{$product->id}")
            ->assertOk()
            ->assertJsonCount(0, 'lines');
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_set_item_rejects_deleted_products_and_quantities_above_available_stock(): void
    {
        [$customer, $product] = $this->customerAndProduct('10.0000', '0', 2);

        $this->actingAs($customer)
            ->putJson("/api/v1/cart/items/{$product->id}", ['quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $product->delete();

        $this->actingAs($customer)
            ->putJson("/api/v1/cart/items/{$product->id}", ['quantity' => 1])
            ->assertNotFound();
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_guest_quote_ignores_client_commerce_values_and_uses_current_tax_and_shipping(): void
    {
        [, $product, $currency, $tax] = $this->customerAndProduct('10.0050', '10.0000', 5);
        $shipping = ShippingMethod::factory()->for($currency)->for($tax)->create(['amount' => '5.0000']);

        $this->postJson('/api/v1/cart/quote', [
            'lines' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_method_id' => $shipping->id,
        ])
            ->assertOk()
            ->assertJsonPath('subtotal', '20.01')
            ->assertJsonPath('tax', '2.50')
            ->assertJsonPath('shipping', '5.00')
            ->assertJsonPath('total', '27.51')
            ->assertJsonPath('currency', 'USD');

        $this->postJson('/api/v1/cart/quote', [
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => '0.01',
                'available' => true,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_merge_adds_quantities_caps_to_stock_and_acknowledges_safe_browser_clear(): void
    {
        [$customer, $product, $currency] = $this->customerAndProduct('8.0000', '0', 4);
        $cart = Cart::factory()->for($customer)->for($currency)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($customer)
            ->postJson('/api/v1/cart/merge', [
                'lines' => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertOk()
            ->assertHeader('X-Cart-Merge-Acknowledged', 'true')
            ->assertJsonPath('lines.0.quantity', 4)
            ->assertJsonPath('lines.0.stock_limit', 4)
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('lines.0.adjustment', 'Quantity was capped at the current stock limit of 4.');
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
    }

    public function test_quote_reports_deleted_and_insufficient_stock_without_trusting_availability(): void
    {
        [, $product] = $this->customerAndProduct('6.0000', '0', 1);
        $deleted = Product::factory()->for($product->currency)->for($product->tax)->for($product->category)->create();
        Inventory::factory()->for($deleted)->create(['stock_on_hand' => 10]);
        $deleted->delete();

        $response = $this->postJson('/api/v1/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $deleted->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('lines.0.available', false)
            ->assertJsonPath('lines.0.stock_limit', 1)
            ->assertJsonPath('lines.1.available', false)
            ->assertJsonPath('lines.1.stock_limit', 0);
    }

    public function test_customer_can_select_and_clear_an_active_shipping_method(): void
    {
        [$customer, , $currency, $tax] = $this->customerAndProduct('10.0000', '0', 1);
        $shipping = ShippingMethod::factory()->for($currency)->for($tax)->create(['amount' => '5.0000']);

        $this->actingAs($customer)
            ->putJson('/api/v1/cart/shipping-method', ['shipping_method_id' => $shipping->id])
            ->assertOk()
            ->assertJsonPath('shipping', '5.00');
        $this->assertDatabaseHas('carts', ['user_id' => $customer->id, 'shipping_method_id' => $shipping->id]);

        $this->actingAs($customer)
            ->putJson('/api/v1/cart/shipping-method', ['shipping_method_id' => null])
            ->assertOk()
            ->assertJsonPath('shipping', '0.00');
        $this->assertDatabaseHas('carts', ['user_id' => $customer->id, 'shipping_method_id' => null]);
    }

    /** @return array{User, Product, Currency, Tax} */
    private function customerAndProduct(string $price, string $taxRate, int $stock): array
    {
        $customer = User::factory()->create();
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create(['rate' => $taxRate]);
        $category = Category::factory()->create();
        $product = Product::factory()->for($currency)->for($tax)->for($category)->create(['price' => $price]);
        Inventory::factory()->for($product)->create(['stock_on_hand' => $stock]);

        return [$customer, $product, $currency, $tax];
    }
}
