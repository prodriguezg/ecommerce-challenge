<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE inventory_adjustments DROP CHECK inventory_adjustments_delta_check');
        DB::statement('ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustments_delta_check CHECK (delta = CAST(new_quantity AS SIGNED) - CAST(previous_quantity AS SIGNED))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE inventory_adjustments DROP CHECK inventory_adjustments_delta_check');
        DB::statement('ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustments_delta_check CHECK (delta = new_quantity - previous_quantity)');
    }
};
