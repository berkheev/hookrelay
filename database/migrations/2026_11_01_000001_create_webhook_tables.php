<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source', 64);
            $table->string('idempotency_key')->nullable();
            $table->string('type')->nullable();
            $table->jsonb('headers');
            $table->text('body');
            $table->timestampTz('received_at');

            $table->unique(['source', 'idempotency_key']);
            $table->index(['source', 'received_at']);
            $table->index('received_at');
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->text('destination');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('locked_until')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->text('error')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->timestampTz('created_at');

            $table->index('delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('events');
    }
};
