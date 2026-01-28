<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE numeric(12,2) USING (resolution_cost / 100.0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE integer USING (round(resolution_cost * 100))');
    }
};
