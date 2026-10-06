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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('missingCategoryPolicies')]
    public function test_creates_all_missing_categories_only_for_create_policy(string $policy, int $accepted, int $warnings, int $categoryCount): void
    {
        $csv = $this->numericCsv([
            ['First', 'CATEGORY-1', 'First missing', '12', '2', '1'],
            ['Second', 'CATEGORY-2', 'Second missing', '12', '2', '1'],
        ]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['unknown_category_policy' => $policy]));

        $response->assertOk()->assertJsonPath('accepted_rows', $accepted)->assertJsonPath('warning_rows', $warnings)->assertJsonPath('rejected_rows', 2 - $accepted);
        $this->assertDatabaseCount('categories', $categoryCount);
        $this->assertDatabaseCount('products', $accepted);
    }

    /** @return array<string, array{string, int, int, int}> */
    public static function missingCategoryPolicies(): array
    {
        return [
            'reject' => ['reject', 0, 0, 0],
            'create' => ['create', 2, 0, 2],
            'uncategorized' => ['uncategorized', 2, 2, 0],
        ];
    }

    #[DataProvider('validNumericRows')]
    public function test_imports_human_formatted_numbers_with_correct_values(string $price, string $weight, string $stock, string $expectedPrice, string $expectedWeight, int $expectedStock): void
    {
        $csv = $this->numericCsv([['Widget', 'NUM-1', '', $price, $weight, $stock]]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv));

        $response->assertOk()->assertJsonPath('accepted_rows', 1)->assertJsonPath('rejected_rows', 0);
        $product = Product::with('inventory')->firstOrFail();
        $this->assertSame($expectedPrice, $product->price);
        $this->assertSame($expectedWeight, $product->weight_kg);
        $this->assertSame($expectedStock, $product->inventory?->stock_on_hand);
    }

    /** @return array<string, array{string, string, string, string, string, int}> */
    public static function validNumericRows(): array
    {
        return [
            'integers' => ['12', '2', '12', '12.0000', '2.0000', 12],
            'zero' => ['0', '0', '0', '0.0000', '0.0000', 0],
            'one decimal' => ['12.5', '2.5', '12', '12.5000', '2.5000', 12],
            'full decimal precision' => ['12.50', '2.5000', '12', '12.5000', '2.5000', 12],
            'two weight decimals' => ['12.50', '2.56', '12', '12.5000', '2.5600', 12],
            'three weight decimals' => ['12.50', '2.567', '12', '12.5000', '2.5670', 12],
            'dollar integer' => ['$12', '2', '12', '12.0000', '2.0000', 12],
            'dollar decimal' => ['$12.50', '2', '12', '12.5000', '2.0000', 12],
            'dollar whitespace' => [" \t$ 12.50 ", " 2.5000\t", ' 12 ', '12.5000', '2.5000', 12],
            'surrounding whitespace' => ["\t12.5 ", " 2.5\t", "\t0 ", '12.5000', '2.5000', 0],
            'grouped decimals' => ['1,234.56', '1,234.5678', '1,000', '1234.5600', '1234.5678', 1000],
            'grouped dollar' => ['$1,234.56', '12,345.6789', '12,345', '1234.5600', '12345.6789', 12345],
            'multiple groups and three digit first group' => ['123,456,789.12', '1,234,567.8901', '123,456,789', '123456789.1200', '1234567.8901', 123456789],
            'leading zeros' => ['00012.50', '00002.5000', '00012', '12.5000', '2.5000', 12],
            'weight and initial stock maximums' => ['12', '99,999,999.9999', '2,147,483,647', '12.0000', '99999999.9999', 2147483647],
        ];
    }

    #[DataProvider('invalidNumericValues')]
    public function test_rejects_only_invalid_numeric_row_with_exact_download_reason(string $field, string $value): void
    {
        $numbers = ['price' => '12', 'weight_kg' => '2', 'stock' => '1'];
        $numbers[$field] = $value;
        $csv = $this->numericCsv([
            ['Invalid', 'INVALID-NUM', 'Missing', $numbers['price'], $numbers['weight_kg'], $numbers['stock']],
            ['Valid', 'VALID-NUM', '', '$12.50', '2.5000', '1,000'],
        ]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['unknown_category_policy' => 'create']));

        $response->assertOk()->assertJsonPath('accepted_rows', 1)->assertJsonPath('rejected_rows', 1);
        $this->assertDatabaseMissing('products', ['normalized_sku' => 'INVALID-NUM']);
        $this->assertDatabaseHas('products', ['normalized_sku' => 'VALID-NUM']);
        $this->assertDatabaseMissing('categories', ['normalized_name' => 'missing']);
        $this->assertDatabaseCount('inventories', 1);
        $report = $this->get('/api/v1/admin/product-imports/'.$response->json('id').'/rejections.csv')->assertOk()->getContent();
        $cells = str_getcsv(explode("\n", trim($report))[1], escape: '');
        $this->assertSame('Invalid '.$field.' value', $cells[6]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidNumericValues(): iterable
    {
        foreach (['price', 'weight_kg', 'stock'] as $field) {
            foreach ([
                'empty' => '', 'whitespace' => " \t ", 'negative' => '-1', 'plus sign' => '+1',
                'exponent' => '1e3', 'NaN' => 'NaN', 'Infinity' => 'Infinity', 'text' => 'free',
                'short group' => '12,34', 'Indian groups' => '1,23,456', 'long group' => '1,0000',
                'long first group' => '1234,567', 'empty group' => '1,,000', 'trailing comma' => '1,000,',
                'locale decimal' => '12,5', 'locale punctuation' => '1.234,56',
                'internal whitespace' => '1 000', 'missing integer' => '.5', 'missing fraction' => '1.',
                'multiple dollar signs' => '$$12', 'trailing dollar sign' => '12$',
            ] as $name => $value) {
                yield $field.' '.$name => [$field, $value];
            }
        }
        yield 'price excess decimals' => ['price', '12.500'];
        yield 'price dollar excess decimals' => ['price', '$12.500'];
        yield 'price dollar only' => ['price', '$ '];
        yield 'weight excess decimals' => ['weight_kg', '2.50000'];
        yield 'stock decimal' => ['stock', '12.0'];
        yield 'weight dollar' => ['weight_kg', '$2'];
        yield 'stock dollar' => ['stock', '$12'];
        yield 'price out of range' => ['price', '1000000000000000'];
        yield 'price grouped out of range' => ['price', '$1,000,000,000,000,000.00'];
        yield 'weight out of range' => ['weight_kg', '100000000'];
        yield 'weight grouped out of range' => ['weight_kg', '100,000,000.0000'];
        yield 'stock out of range' => ['stock', '4294967296'];
        yield 'stock grouped out of range' => ['stock', '4,294,967,296'];
        yield 'initial stock delta out of range' => ['stock', '2,147,483,648'];
        yield 'price huge value' => ['price', str_repeat('9', 100)];
    }

    public function test_rejection_report_aggregates_every_invalid_numeric_reason_once(): void
    {
        $csv = $this->numericCsv([['Invalid', 'MULTI-NUM', '', '$-12.500', 'NaN', '1,00']]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv));

        $response->assertOk()->assertJsonPath('accepted_rows', 0)->assertJsonPath('rejected_rows', 1);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $report = $this->get('/api/v1/admin/product-imports/'.$response->json('id').'/rejections.csv')->assertOk()->getContent();
        $cells = str_getcsv(explode("\n", trim($report))[1], escape: '');
        $this->assertSame(['Invalid', 'MULTI-NUM', '', '$-12.500', 'NaN', '1,00', 'Invalid price value; Invalid weight_kg value; Invalid stock value'], $cells);
    }

    #[DataProvider('normalizedUpdateModes')]
    public function test_normalized_updates_preserve_import_mode_and_stock_override_semantics(string $mode, bool $override): void
    {
        $product = $this->existingProduct('UPDATE-NUM', 9);
        $csv = $this->numericCsv([['Updated', 'UPDATE-NUM', '', '$1,234.56', '2.5678', '1,000']]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => $mode, 'override_stock' => $override, 'confirm_stock_override' => $override]));

        $response->assertOk()->assertJsonPath('accepted_rows', 1);
        $product->refresh();
        $this->assertSame('1234.5600', $product->price);
        $this->assertSame('2.5678', $product->weight_kg);
        $this->assertSame($override ? 1000 : 9, $product->inventory?->stock_on_hand);
        $this->assertSame($override ? 1 : 0, $product->inventoryAdjustments()->count());
    }

    /** @return array<string, array{string, bool}> */
    public static function normalizedUpdateModes(): array
    {
        return [
            'update without override' => ['update_only', false],
            'update with override' => ['update_only', true],
            'upsert without override' => ['upsert', false],
            'upsert with override' => ['upsert', true],
        ];
    }

    #[DataProvider('stockDeltaBoundaries')]
    public function test_stock_override_respects_existing_signed_adjustment_range(int $previousStock, string $stock, bool $accepted): void
    {
        $product = $this->existingProduct('DELTA-NUM', $previousStock, ['category_id' => null]);
        $csv = $this->numericCsv([['Updated', 'DELTA-NUM', 'New category', '$12.50', '2.5678', $stock]]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only', 'unknown_category_policy' => 'create', 'override_stock' => true, 'confirm_stock_override' => true]));

        $response->assertOk()->assertJsonPath('accepted_rows', (int) $accepted)->assertJsonPath('rejected_rows', (int) ! $accepted);
        $product->refresh();
        $this->assertSame($accepted ? 'Updated' : 'Old Product', $product->name);
        $this->assertSame($accepted ? (int) str_replace(',', '', $stock) : $previousStock, $product->inventory?->stock_on_hand);
        $this->assertDatabaseCount('categories', (int) $accepted);
        $this->assertSame((int) $accepted, $product->inventoryAdjustments()->count());
        if (! $accepted) {
            $report = $this->get('/api/v1/admin/product-imports/'.$response->json('id').'/rejections.csv')->assertOk()->getContent();
            $cells = str_getcsv(explode("\n", trim($report))[1], escape: '');
            $this->assertSame('Invalid stock value', $cells[6]);
        }
    }

    /** @return array<string, array{int, string, bool}> */
    public static function stockDeltaBoundaries(): array
    {
        return [
            'positive maximum delta' => [2147483648, '4,294,967,295', true],
            'positive excess delta' => [2147483647, '4,294,967,295', false],
            'negative minimum delta' => [2147483648, '0', true],
            'negative excess delta' => [2147483649, '0', false],
        ];
    }

    public function test_unsigned_stock_limit_is_validated_without_override(): void
    {
        $product = $this->existingProduct('MAX-STOCK', 9);
        $csv = $this->numericCsv([['Updated', 'MAX-STOCK', '', '$12.50', '2.5678', '4,294,967,295']]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only']));

        $response->assertOk()->assertJsonPath('accepted_rows', 1)->assertJsonPath('rejected_rows', 0);
        $this->assertSame('Updated', $product->fresh()->name);
        $this->assertSame(9, $product->inventory?->fresh()->stock_on_hand);
        $this->assertSame(0, $product->inventoryAdjustments()->count());
    }

    public function test_invalid_stock_rejects_update_even_without_stock_override(): void
    {
        $product = $this->existingProduct('INVALID-STOCK', 9);
        $csv = $this->numericCsv([['Updated', 'INVALID-STOCK', '', '$12.50', '2.5678', '1,00']]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv, ['mode' => 'update_only']));

        $response->assertOk()->assertJsonPath('rejected_rows', 1)->assertJsonPath('accepted_rows', 0);
        $this->assertSame('Old Product', $product->fresh()->name);
        $this->assertSame(9, $product->inventory?->fresh()->stock_on_hand);
        $this->assertSame(0, $product->inventoryAdjustments()->count());
        $report = $this->get('/api/v1/admin/product-imports/'.$response->json('id').'/rejections.csv')->assertOk()->getContent();
        $cells = str_getcsv(explode("\n", trim($report))[1], escape: '');
        $this->assertSame('Invalid stock value', $cells[6]);
    }

    public function test_mysql_preserves_price_precision_at_the_database_limit(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Exact DECIMAL persistence requires MySQL.');
        }
        $csv = $this->numericCsv([['Maximum', 'MAX-NUM', '', '$999,999,999,999,999.99', '99,999,999.9999', '2,147,483,647']]);

        $response = $this->post('/api/v1/admin/product-imports', $this->payload($csv));

        $response->assertOk()->assertJsonPath('accepted_rows', 1);
        $product = Product::with('inventory')->firstOrFail();
        $this->assertSame('999999999999999.9900', $product->price);
        $this->assertSame('99999999.9999', $product->weight_kg);
        $this->assertSame(2147483647, $product->inventory?->stock_on_hand);
    }

    /** @param list<list<string>> $rows */
    private function numericCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+b');
        fputcsv($stream, ['name', 'sku', 'category', 'price', 'weight_kg', 'stock'], escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
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
