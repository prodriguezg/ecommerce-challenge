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
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence_number')->unique();
            $table->string('order_code')->unique();
            $table->foreignUlid('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('normalized_email');
            $table->foreignUlid('currency_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('status', 32);
            $table->decimal('subtotal', 19, 4)->unsigned();
            $table->decimal('product_tax', 19, 4)->unsigned();
            $table->decimal('shipping_amount', 19, 4)->unsigned();
            $table->decimal('shipping_tax', 19, 4)->unsigned();
            $table->decimal('grand_total', 19, 4)->unsigned();
            $table->string('shipping_method_name');
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->timestamp('guest_token_expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['customer_user_id', 'created_at']);
            $table->index(['normalized_email', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
