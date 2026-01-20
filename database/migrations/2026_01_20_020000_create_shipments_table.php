<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained();
            $table->foreignUuid('source_location_id')->constrained('locations');
            $table->foreignUuid('target_location_id')->constrained('locations');
            $table->string('status'); // 'pending', 'in_transit', 'delivered', 'failed'
            $table->integer('sequence_index'); // 0 = first leg
            $table->date('arrival_date')->nullable();
            $table->integer('arrival_day')->nullable(); // Simulation day
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
