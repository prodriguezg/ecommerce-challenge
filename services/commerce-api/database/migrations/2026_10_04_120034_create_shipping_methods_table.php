<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('active_normalized_name')->nullable()
                ->storedAs('case when deleted_at is null then normalized_name else null end');
            $table->decimal('amount', 19, 4)->unsigned();
            $table->foreignUlid('currency_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('active_normalized_name');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shipping_methods ADD CONSTRAINT shipping_methods_amount_check CHECK (amount >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
