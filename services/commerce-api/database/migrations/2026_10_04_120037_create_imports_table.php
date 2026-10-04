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
        Schema::create('imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('mode', 24);
            $table->string('unknown_category_policy', 24);
            $table->boolean('stock_override')->default(false);
            $table->timestamp('stock_override_confirmed_at')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
