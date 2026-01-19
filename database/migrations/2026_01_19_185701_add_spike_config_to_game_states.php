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
        Schema::table('game_states', function (Blueprint $table) {
            // Track last-started day per spike type (cooldown is enforced relative to start days)
            // Format: {"demand": 5, "blizzard": 3} = last start day
            $table->json('spike_cooldowns')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_states', function (Blueprint $table) {
            $table->dropColumn('spike_cooldowns');
        });
    }
};
