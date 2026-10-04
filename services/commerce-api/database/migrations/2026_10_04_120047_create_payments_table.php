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
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('status', 32);
            $table->decimal('amount', 19, 4)->unsigned();
            $table->foreignUlid('currency_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('provider_code')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
