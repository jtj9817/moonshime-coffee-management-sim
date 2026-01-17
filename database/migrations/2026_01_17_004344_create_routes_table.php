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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('source_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('locations')->cascadeOnDelete();
            $table->string('transport_mode'); // e.g., Truck, Air, Ship
            $table->json('weights'); // {cost: 10, time: 5}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};