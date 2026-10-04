<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reference_seeder_creates_only_the_documented_records(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(ReferenceDataSeeder::class);

        $this->assertDatabaseCount('currencies', 1);
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'rate_to_base' => '1.000000000000', 'is_base' => true, 'minor_units' => 2]);
        $this->assertDatabaseCount('taxes', 1);
        $this->assertDatabaseHas('taxes', ['name' => 'Standard', 'rate' => '10.0000']);
        $this->assertDatabaseCount('shipping_methods', 2);
        $this->assertDatabaseHas('shipping_methods', ['name' => 'Ground', 'amount' => '5.0000']);
        $this->assertDatabaseHas('shipping_methods', ['name' => 'Air', 'amount' => '15.0000']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_active_category_names_are_case_insensitively_unique_and_reusable_after_deletion(): void
    {
        Category::factory()->create(['name' => '  Outdoor   Gear '])->delete();

        $replacement = Category::factory()->create(['name' => 'outdoor gear']);

        $this->assertSame('outdoor gear', $replacement->normalized_name);

        $this->expectException(QueryException::class);
        Category::factory()->create(['name' => 'OUTDOOR GEAR']);
    }

    public function test_product_sku_remains_unique_after_soft_deletion(): void
    {
        Product::factory()->create(['sku' => ' permanent-42 '])->delete();

        $this->expectException(QueryException::class);
        Product::factory()->create(['sku' => 'PERMANENT-42']);
    }

    public function test_database_allows_only_one_administrator(): void
    {
        User::factory()->admin()->create();

        $this->expectException(QueryException::class);
        User::factory()->admin()->create();
    }

    public function test_mysql_enforces_bounded_tax_rates(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL constraint coverage runs in the MySQL integration job.');
        }

        $this->expectException(QueryException::class);
        DB::table('taxes')->insert([
            'id' => (string) Str::ulid(),
            'name' => 'Invalid',
            'normalized_name' => 'invalid',
            'rate' => '100.0001',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_mysql_enforces_non_negative_shipping_amounts(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL constraint coverage runs in the MySQL integration job.');
        }

        $currency = Currency::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('shipping_methods')->insert([
            'id' => (string) Str::ulid(),
            'name' => 'Invalid',
            'normalized_name' => 'invalid',
            'amount' => '-0.0001',
            'currency_id' => $currency->id,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_normalized_emails_are_unique(): void
    {
        User::factory()->create(['email' => 'Customer@Example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'customer@example.com']);
    }

    public function test_seeded_shipping_references_usd_and_standard_tax(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $usd = Currency::query()->where('code', 'USD')->sole();
        $tax = Tax::query()->where('normalized_name', 'standard')->sole();

        ShippingMethod::query()->each(function (ShippingMethod $method) use ($usd, $tax): void {
            $this->assertSame($usd->id, $method->currency_id);
            $this->assertSame($tax->id, $method->tax_id);
        });
    }
}
