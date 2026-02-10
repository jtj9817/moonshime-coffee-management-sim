<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('source_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->json('items');
            $table->integer('next_run_day');
            $table->integer('interval_days')->nullable();
            $table->string('cron_expression')->nullable();
            $table->boolean('auto_submit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('last_run_day')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'next_run_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_orders');
    }
};
