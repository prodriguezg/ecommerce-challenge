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
        Schema::create('product_sku_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_sku')->unique();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('products')->orderBy('id')->each(function (object $product): void {
            DB::table('product_sku_reservations')->insert([
                'normalized_sku' => $product->normalized_sku,
                'product_id' => $product->id,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sku_reservations');
    }
};
