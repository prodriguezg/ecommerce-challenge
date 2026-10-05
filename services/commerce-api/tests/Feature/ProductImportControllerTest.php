<?php

namespace Tests\Feature;

use App\Enums\ImportMode;
use App\Enums\ReservationStatus;
use App\Enums\UnknownCategoryPolicy;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Currency::factory()->usd()->create();
        $this->actingAs($this->admin);
    }

    public function test_admin_imports_valid_rows_independently_and_reads_metadata(): void
    {
        $category = Category::factory()->create(['name' => 'Tools', 'slug' => 'tools']);
        $csv = "name,sku,description,category,price,stock,weight_kg\nHammer,HAM-1,Steel,Tools,12.50,5,1.25\n,INVALID,,Tools,4.00,2,0.5\n";

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv));

        $response->assertOk()->assertJsonPath('status', 'completed_with_rejections')->assertJsonPath('total_rows', 2)->assertJsonPath('accepted_rows', 1)->assertJsonPath('warning_rows', 0)->assertJsonPath('rejected_rows', 1);
        $this->assertDatabaseHas('products', ['normalized_sku' => 'HAM-1', 'category_id' => $category->getKey()]);
        $this->assertDatabaseHas('inventories', ['stock_on_hand' => 5]);
        $this->assertDatabaseHas('inventory_adjustments', ['reason' => 'csv_import', 'new_quantity' => 5]);
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'product_import.completed', 'actor_user_id' => $this->admin->getKey()]);
    }

    public function test_pre_scan_rejects_every_duplicate_sku_occurrence_and_neutralizes_formula_cells(): void
    {
        $csv = "name,sku,description,category,price,stock,weight_kg\n=First,DUP-1,,,10.00,1,0.5\nSecond, dup-1 ,,,12.00,2,0.6\n";

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['unknown_category_policy' => 'uncategorized']));

        $response->assertOk()->assertJsonPath('accepted_rows', 0)->assertJsonPath('rejected_rows', 2);
        $this->assertDatabaseMissing('products', ['normalized_sku' => 'DUP-1']);
        $report = (string) Import::firstOrFail()->rejection_report;
        $this->assertStringContainsString("'=First", $report);
        $this->assertSame(2, substr_count($report, 'every occurrence was rejected'));
    }

    public function test_unknown_category_policies_import_with_warning_or_create_category(): void
    {
        $csv = "name,sku,description,category,price,stock,weight_kg\nWidget,WID-1,,Missing,10.00,1,0.5\n";

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['unknown_category_policy' => 'uncategorized']));

        $response->assertOk()->assertJsonPath('accepted_rows', 1)->assertJsonPath('warning_rows', 1);
        $this->assertNull(Product::firstOrFail()->category_id);
        $this->assertDatabaseMissing('categories', ['normalized_name' => 'missing']);
    }

    public function test_create_category_policy_creates_category_and_assigns_product(): void
    {
        $csv = "name,sku,description,category,price,stock,weight_kg\nWidget,WID-2,,New Category,10.00,1,0.5\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['unknown_category_policy' => 'create']))->assertOk()->assertJsonPath('accepted_rows', 1);

        $category = Category::where('normalized_name', 'new category')->firstOrFail();
        $this->assertSame($category->getKey(), Product::firstOrFail()->category_id);
    }

    public function test_update_clears_optional_fields_and_preserves_stock_without_override(): void
    {
        $product = $this->existingProduct('UPDATE-1', 9, ['description' => 'Old', 'category_id' => Category::factory()->create()->getKey()]);
        $csv = "name,sku,description,category,price,stock,weight_kg\nUpdated,UPDATE-1,,,15.00,2,1.5\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only']))->assertOk()->assertJsonPath('accepted_rows', 1);

        $product->refresh();
        $this->assertNull($product->description);
        $this->assertNull($product->category_id);
        $this->assertSame(9, $product->inventory?->stock_on_hand);
        $this->assertSame(0, $product->inventoryAdjustments()->count());
    }

    public function test_confirmed_stock_override_updates_stock_and_records_adjustment(): void
    {
        $product = $this->existingProduct('UPDATE-2', 9);
        $csv = "name,sku,description,category,price,stock,weight_kg\nUpdated,UPDATE-2,,,15.00,3,1.5\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only', 'override_stock' => true, 'confirm_stock_override' => true]))->assertOk()->assertJsonPath('accepted_rows', 1);

        $this->assertSame(3, $product->inventory?->fresh()->stock_on_hand);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->getKey(), 'previous_quantity' => 9, 'new_quantity' => 3, 'reason' => 'csv_import']);
    }

    public function test_stock_override_below_active_reservation_rejects_row_without_changes(): void
    {
        $product = $this->existingProduct('UPDATE-3', 9);
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Active]);
        ReservationItem::factory()->create(['reservation_id' => $reservation->getKey(), 'product_id' => $product->getKey(), 'inventory_id' => $product->inventory?->getKey(), 'quantity' => 4]);
        $csv = "name,sku,description,category,price,stock,weight_kg\nUpdated,UPDATE-3,,,15.00,3,1.5\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only', 'override_stock' => true, 'confirm_stock_override' => true]))->assertOk()->assertJsonPath('rejected_rows', 1);

        $this->assertSame(9, $product->inventory?->fresh()->stock_on_hand);
        $this->assertSame('Old Product', $product->fresh()->name);
    }

    public function test_soft_deleted_sku_is_always_rejected(): void
    {
        $product = $this->existingProduct('DELETED-1', 2);
        $product->delete();
        $csv = "name,sku,description,category,price,stock,weight_kg\nReplacement,DELETED-1,,,10.00,1,0.5\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'upsert']))->assertOk()->assertJsonPath('rejected_rows', 1);

        $this->assertSame('Old Product', $product->fresh()->name);
        $this->assertStringContainsString('Soft-deleted SKUs', (string) Import::firstOrFail()->rejection_report);
    }

    public function test_whole_file_header_error_is_rejected_before_import_record(): void
    {
        $csv = "name,sku,price,stock,weight_kg,unexpected\nOne,ONE-1,1.00,1,1,x\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv))->assertUnprocessable()->assertJsonPath('code', 'csv_invalid_header');

        $this->assertDatabaseCount('imports', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_configured_byte_limit_returns_413_before_processing(): void
    {
        config(['api.csv_import.max_bytes' => 10]);

        $this->post('/api/v1/admin/product-imports', $this->payload("name,sku,price,stock,weight_kg\nOne,ONE-1,1.00,1,1\n"))->assertStatus(413)->assertJsonPath('code', 'csv_file_too_large');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_configured_row_limit_and_invalid_utf8_are_whole_file_errors(): void
    {
        config(['api.csv_import.max_rows' => 1]);
        $csv = "name,sku,price,stock,weight_kg\nOne,ONE-1,1.00,1,1\nTwo,TWO-1,2.00,2,2\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv))->assertUnprocessable()->assertJsonPath('code', 'csv_row_limit_exceeded');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_invalid_utf8_is_rejected_before_row_processing(): void
    {
        $csv = "name,sku,price,stock,weight_kg\nBad\xFF,BAD-1,1.00,1,1\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv))->assertUnprocessable()->assertJsonPath('code', 'csv_invalid_encoding');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_stock_override_requires_explicit_confirmation(): void
    {
        $csv = "name,sku,price,stock,weight_kg\nOne,ONE-1,1.00,1,1\n";

        $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['override_stock' => true]))->assertUnprocessable()->assertJsonValidationErrors('confirm_stock_override');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_customer_cannot_import_products(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/api/v1/admin/product-imports', $this->payload("name,sku,price,stock,weight_kg\nOne,ONE-1,1.00,1,1\n"))->assertForbidden();

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_rejection_download_has_safe_headers_and_content(): void
    {
        $import = Import::factory()->create(['actor_user_id' => $this->admin->getKey(), 'rejection_report' => "name,reason\nSafe,Rejected\n", 'rejected_count' => 1]);

        $response = $this->get("/api/v1/admin/product-imports/{$import->getKey()}/rejections.csv");

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'attachment; filename="product-import-'.$import->getKey().'-rejections.csv"')->assertSee('Safe,Rejected', false);
    }

    public function test_bundled_challenge_csv_completes_without_fixed_row_count_assumption(): void
    {
        $file = new UploadedFile(base_path('../../docs/examples/code-challenge-products.csv'), 'code-challenge-products.csv', 'text/csv', null, true);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($file, ['unknown_category_policy' => 'create']));

        $response->assertOk();
        $total = (int) $response->json('total_rows');
        $accepted = (int) $response->json('accepted_rows');
        $rejected = (int) $response->json('rejected_rows');
        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThan(0, $accepted);
        $this->assertGreaterThan(0, $rejected);
        $this->assertSame($total, $accepted + $rejected);
    }

    /** @param string|UploadedFile $csv @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(string|UploadedFile $csv, array $overrides = []): array
    {
        return array_merge(['file' => is_string($csv) ? UploadedFile::fake()->createWithContent('products.csv', $csv) : $csv, 'mode' => ImportMode::CreateOnly->value, 'unknown_category_policy' => UnknownCategoryPolicy::Reject->value, 'override_stock' => false], $overrides);
    }

    /** @param array<string, mixed> $attributes */
    private function existingProduct(string $sku, int $stock, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku, 'name' => 'Old Product'], $attributes));
        $product->inventory()->create(['stock_on_hand' => $stock, 'version' => 1]);
        DB::table('product_sku_reservations')->insert(['normalized_sku' => $product->normalized_sku, 'product_id' => $product->getKey(), 'created_at' => now()]);

        return $product->fresh(['inventory']);
    }
}
