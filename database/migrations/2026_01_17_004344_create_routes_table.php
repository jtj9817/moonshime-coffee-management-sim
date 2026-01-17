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
            $table->integer('cost');
            $table->integer('transit_days');
            $table->integer('capacity')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->boolean('weather_vulnerability')->default(false);
            $table->timestamps();

            $table->unique(['source_id', 'target_id', 'transport_mode'], 'routes_source_target_mode_unique');
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