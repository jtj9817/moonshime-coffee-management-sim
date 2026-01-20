<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN total_cost TYPE numeric(12,2) USING (total_cost / 100.0)');
        DB::statement('ALTER TABLE order_items ALTER COLUMN cost_per_unit TYPE numeric(12,2) USING (cost_per_unit / 100.0)');
        DB::statement('ALTER TABLE game_states ALTER COLUMN cash TYPE numeric(12,2) USING (cash / 100.0)');
        DB::statement('ALTER TABLE routes ALTER COLUMN cost TYPE numeric(12,2) USING (cost / 100.0)');

        DB::statement('ALTER TABLE game_states ALTER COLUMN cash SET DEFAULT 10000.00');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN total_cost TYPE integer USING (round(total_cost * 100))');
        DB::statement('ALTER TABLE order_items ALTER COLUMN cost_per_unit TYPE integer USING (round(cost_per_unit * 100))');
        DB::statement('ALTER TABLE game_states ALTER COLUMN cash TYPE bigint USING (round(cash * 100))');
        DB::statement('ALTER TABLE routes ALTER COLUMN cost TYPE integer USING (round(cost * 100))');

        DB::statement('ALTER TABLE game_states ALTER COLUMN cash SET DEFAULT 1000000');
    }
};
