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
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('recipient_name');
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('city');
            $table->string('region');
            $table->string('postal_code', 32);
            $table->char('country_code', 2);
            $table->string('phone', 32);
            $table->string('email');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
