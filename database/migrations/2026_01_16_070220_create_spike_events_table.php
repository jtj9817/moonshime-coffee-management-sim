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
        Schema::create('spike_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->decimal('magnitude', 8, 2);
            $table->integer('duration');
            $table->foreignUuid('location_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignUuid('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('starts_at_day');
            $table->integer('ends_at_day');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spike_events');
    }
};