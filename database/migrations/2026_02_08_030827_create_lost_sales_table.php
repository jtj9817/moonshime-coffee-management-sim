<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->integer('day');
            $table->integer('quantity_lost');
            $table->bigInteger('potential_revenue_lost'); // integer cents
            $table->timestamps();

            $table->index(['user_id', 'day']);
            $table->index(['location_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_sales');
    }
};
