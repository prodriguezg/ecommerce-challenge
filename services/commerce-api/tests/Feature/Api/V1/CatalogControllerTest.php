<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\ShippingMethod;
use App\Models\Tax;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_searches_active_products_case_insensitively_across_catalog_fields(): void
    {
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create();
        $category = Category::factory()->create(['name' => 'Trail Gear']);
        $nameMatch = Product::factory()->for($currency)->for($tax)->for($category)->create([
            'name' => 'Alpine Backpack',
            'sku' => 'PACK-001',
            'description' => 'Lightweight carry system',
        ]);
        $descriptionMatch = Product::factory()->for($currency)->for($tax)->create([
            'name' => 'Camp Bottle',
            'sku' => 'BOTTLE-001',
            'description' => 'Built for ALPINE weather',
        ]);
        $deleted = Product::factory()->for($currency)->for($tax)->create(['name' => 'Alpine Deleted']);
        Inventory::factory()->for($nameMatch)->create(['stock_on_hand' => 3]);
        Inventory::factory()->for($descriptionMatch)->create(['stock_on_hand' => 2]);
        Inventory::factory()->for($deleted)->create(['stock_on_hand' => 5]);
        $deleted->delete();

        $response = $this->getJson('/api/v1/products?q=alpine');

        $response->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonFragment(['id' => $nameMatch->id])
            ->assertJsonFragment(['id' => $descriptionMatch->id])
            ->assertJsonMissing(['id' => $deleted->id]);
    }

    public function test_filters_sorts_and_paginates_products_with_documented_page_sizes(): void
    {
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create();
        $category = Category::factory()->create();
        $expensive = Product::factory()->for($currency)->for($tax)->for($category)->create([
            'name' => 'Expensive',
            'price' => '25.0000',
        ]);
        $cheap = Product::factory()->for($currency)->for($tax)->for($category)->create([
            'name' => 'Cheap',
            'price' => '10.0000',
        ]);
        Inventory::factory()->for($expensive)->create(['stock_on_hand' => 5]);
        Inventory::factory()->for($cheap)->create(['stock_on_hand' => 0]);

        $response = $this->getJson("/api/v1/products?category={$category->id}&min_price=20&in_stock=true&sort=price&direction=desc&per_page=10");

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $expensive->id)
            ->assertJsonPath('items.0.price', '25.00')
            ->assertJsonPath('items.0.price_excludes_tax', true)
            ->assertJsonPath('items.0.in_stock', true)
            ->assertJsonPath('pagination.per_page', 10);

        $this->getJson('/api/v1/products?per_page=25&sort=price%20desc')
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_product_detail_and_storefront_reference_lists_exclude_deleted_records(): void
    {
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create();
        $activeCategory = Category::factory()->create(['name' => 'Active']);
        $deletedCategory = Category::factory()->create(['name' => 'Deleted']);
        $activeShipping = ShippingMethod::factory()->for($currency)->for($tax)->create(['name' => 'Ground']);
        $deletedShipping = ShippingMethod::factory()->for($currency)->for($tax)->create(['name' => 'Drone']);
        $product = Product::factory()->for($currency)->for($tax)->for($activeCategory)->create([
            'name' => 'Detailed product',
            'price' => '12.5000',
        ]);
        Inventory::factory()->for($product)->create(['stock_on_hand' => 1]);
        $deletedCategory->delete();
        $deletedShipping->delete();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('price', '12.50')
            ->assertJsonPath('currency', 'USD');

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['id' => $activeCategory->id])
            ->assertJsonMissing(['id' => $deletedCategory->id]);

        $this->getJson('/api/v1/shipping-methods')
            ->assertOk()
            ->assertJsonFragment(['id' => $activeShipping->id])
            ->assertJsonMissing(['id' => $deletedShipping->id]);

        $product->delete();

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    public function test_in_stock_filter_subtracts_only_live_active_reservations(): void
    {
        $currency = Currency::factory()->usd()->create();
        $tax = Tax::factory()->create();
        $product = Product::factory()->for($currency)->for($tax)->create();
        $inventory = Inventory::factory()->for($product)->create(['stock_on_hand' => 2]);
        $reservation = Reservation::factory()->create(['expires_at' => now()->addMinute()]);
        ReservationItem::factory()->for($reservation)->for($inventory)->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->getJson('/api/v1/products?in_stock=true')
            ->assertOk()
            ->assertJsonMissing(['id' => $product->id]);

        $reservation->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/products?in_stock=true')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id]);
    }
}
