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
        Schema::create('demand_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('day');
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('requested_quantity');
            $table->integer('fulfilled_quantity');
            $table->integer('lost_quantity');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('lost_revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'day']);
            $table->index(['location_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_events');
    }
};
