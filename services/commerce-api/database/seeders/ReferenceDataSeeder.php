<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ShippingMethod;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usd = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'rate_to_base' => '1.000000000000',
                'is_base' => true,
                'minor_units' => 2,
                'rate_updated_at' => '2026-01-01 00:00:00',
            ],
        );

        $standardTax = Tax::query()->updateOrCreate(
            ['normalized_name' => 'standard', 'deleted_at' => null],
            ['name' => 'Standard', 'rate' => '10.0000', 'version' => 1],
        );

        foreach (['Ground' => '5.0000', 'Air' => '15.0000'] as $name => $amount) {
            ShippingMethod::query()->updateOrCreate(
                ['normalized_name' => strtolower($name), 'deleted_at' => null],
                [
                    'name' => $name,
                    'amount' => $amount,
                    'currency_id' => $usd->id,
                    'tax_id' => $standardTax->id,
                    'version' => 1,
                ],
            );
        }
    }
}
