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
        Schema::table('spike_events', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('meta');
            $table->timestamp('mitigated_at')->nullable()->after('acknowledged_at');
            $table->timestamp('resolved_at')->nullable()->after('mitigated_at');
            $table->string('resolved_by')->nullable()->after('resolved_at'); // 'time' or 'player'
            $table->integer('resolution_cost')->nullable()->after('resolved_by'); // cost in cents
            $table->json('action_log')->nullable()->after('resolution_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spike_events', function (Blueprint $table) {
            $table->dropColumn([
                'acknowledged_at',
                'mitigated_at',
                'resolved_at',
                'resolved_by',
                'resolution_cost',
                'action_log',
            ]);
        });
    }
};
