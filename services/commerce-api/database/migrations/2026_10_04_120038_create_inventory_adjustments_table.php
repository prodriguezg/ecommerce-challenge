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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('previous_quantity');
            $table->unsignedInteger('new_quantity');
            $table->integer('delta');
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('import_id')->nullable()->constrained('imports')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustments_reason_check CHECK (reason IN ('stock_received', 'correction', 'damaged_or_lost', 'customer_return', 'other', 'csv_import'))");
            DB::statement("ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustments_note_check CHECK (reason <> 'other' OR note IS NOT NULL)");
            DB::statement('ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustments_delta_check CHECK (delta = new_quantity - previous_quantity)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
