<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_daily_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained()->cascadeOnDelete();
            $table->integer('day');
            $table->bigInteger('revenue')->default(0);            // integer cents
            $table->bigInteger('cogs')->default(0);               // integer cents
            $table->bigInteger('opex')->default(0);               // integer cents
            $table->bigInteger('net_profit')->default(0);         // integer cents
            $table->integer('units_sold')->default(0);
            $table->integer('stockouts')->default(0);
            $table->decimal('satisfaction', 5, 2)->default(100);  // percentage 0-100
            $table->timestamps();

            $table->unique(['user_id', 'location_id', 'day']);
            $table->index(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_daily_metrics');
    }
};
