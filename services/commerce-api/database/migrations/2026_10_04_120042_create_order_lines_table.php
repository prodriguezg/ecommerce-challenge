<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku');
            $table->unsignedInteger('quantity');
            $table->decimal('weight_kg', 12, 4)->unsigned();
            $table->decimal('unit_price', 19, 4)->unsigned();
            $table->decimal('line_subtotal', 19, 4)->unsigned();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 7, 4)->unsigned()->default(0);
            $table->decimal('tax_amount', 19, 4)->unsigned()->default(0);
            $table->decimal('line_total', 19, 4)->unsigned();
            $table->foreignUlid('currency_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
