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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('sku');
            $table->string('normalized_sku')->unique();
            $table->text('description')->nullable();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('price', 19, 4)->unsigned();
            $table->foreignUlid('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 12, 4)->unsigned();
            $table->string('image_path')->nullable();
            $table->string('image_mime_type', 64)->nullable();
            $table->unsignedBigInteger('image_size')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'deleted_at', 'name']);
            $table->index(['price', 'deleted_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_weight_check CHECK (weight_kg >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_name_check CHECK (length(trim(name)) > 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_sku_check CHECK (length(trim(sku)) > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
