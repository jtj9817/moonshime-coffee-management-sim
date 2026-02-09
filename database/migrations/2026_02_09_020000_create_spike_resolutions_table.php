<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spike_resolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('spike_event_id')->constrained('spike_events')->cascadeOnDelete();
            $table->string('action_type'); // resolve_early, mitigate, acknowledge
            $table->string('action_detail')->nullable(); // specific mitigation action
            $table->bigInteger('cost_cents')->default(0);
            $table->json('effect')->nullable(); // what changed (magnitude reduction, route re-enabled, etc.)
            $table->integer('game_day');
            $table->timestamps();

            $table->index(['user_id', 'spike_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spike_resolutions');
    }
};
