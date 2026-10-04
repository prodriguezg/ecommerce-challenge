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
        Schema::create('currencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8);
            $table->decimal('rate_to_base', 24, 12)->unsigned();
            $table->boolean('is_base')->default(false);
            $table->unsignedTinyInteger('minor_units');
            $table->timestamp('rate_updated_at');
            $table->unsignedTinyInteger('active_base_singleton')->nullable()
                ->storedAs('case when deleted_at is null and is_base = 1 then 1 else null end');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('active_base_singleton');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
