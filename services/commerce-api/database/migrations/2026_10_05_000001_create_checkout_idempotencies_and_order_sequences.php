<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value');
        });

        DB::table('order_sequences')->insert([
            'name' => 'orders',
            'next_value' => (int) config('api.checkout.order_number_start'),
        ]);

        Schema::create('checkout_idempotencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('context_hash', 64);
            $table->string('idempotency_key');
            $table->string('request_hash', 64);
            $table->foreignUlid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamps();
            $table->unique(['context_hash', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotencies');
        Schema::dropIfExists('order_sequences');
    }
};
