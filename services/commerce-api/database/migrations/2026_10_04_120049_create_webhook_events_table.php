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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider_event_id')->unique();
            $table->foreignUlid('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider_payment_id')->index();
            $table->string('event_type', 64);
            $table->string('status', 32);
            $table->char('payload_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
