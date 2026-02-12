<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('spike_events', function (Blueprint $table) {
                $table->decimal('resolution_cost', 12, 2)->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE numeric(12,2) USING (resolution_cost / 100.0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('spike_events', function (Blueprint $table) {
                $table->integer('resolution_cost')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE integer USING (round(resolution_cost * 100))');
        }
    }
};
