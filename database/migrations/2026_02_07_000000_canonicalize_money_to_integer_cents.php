<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 0 – Monetary Unit Canonicalization
 *
 * Converts ALL monetary columns to bigint (integer cents).
 * After this migration every money value in the database represents cents.
 *
 * Columns touched by the earlier "convert_money_columns_to_decimal" migration
 * (game_states.cash, orders.total_cost, order_items.cost_per_unit, routes.cost)
 * are currently numeric(12,2). The code already writes cash as 1000000 (cents written directly)
 * so we cast directly without multiplying. For orders/items/routes the stored
 * values are in dollars (the previous migration divided by 100), so we multiply
 * back to cents.
 *
 * Columns that were always decimal (products, demand_events) are also multiplied
 * by 100 to convert from dollars to cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
             Schema::table('game_states', function (Blueprint $table) {
                $table->bigInteger('cash')->default(1000000)->change();
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->bigInteger('total_cost')->change();
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->bigInteger('cost_per_unit')->change();
            });
            Schema::table('routes', function (Blueprint $table) {
                $table->bigInteger('cost')->change();
            });
            Schema::table('products', function (Blueprint $table) {
                $table->bigInteger('unit_price')->default(0)->change();
                $table->bigInteger('storage_cost')->default(0)->change();
            });
            Schema::table('demand_events', function (Blueprint $table) {
                $table->bigInteger('unit_price')->default(0)->change();
                $table->bigInteger('revenue')->default(0)->change();
                $table->bigInteger('lost_revenue')->default(0)->change();
            });
             Schema::table('spike_events', function (Blueprint $table) {
                $table->bigInteger('resolution_cost')->nullable()->change();
            });

        } else {
            // game_states.cash – already holds 1000000 (cents written directly)
            DB::statement('ALTER TABLE game_states ALTER COLUMN cash TYPE bigint USING round(cash)::bigint');
            DB::statement('ALTER TABLE game_states ALTER COLUMN cash SET DEFAULT 1000000');

            // orders.total_cost – decimal dollars → integer cents
            DB::statement('ALTER TABLE orders ALTER COLUMN total_cost TYPE bigint USING round(total_cost * 100)::bigint');

            // order_items.cost_per_unit – decimal dollars → integer cents
            DB::statement('ALTER TABLE order_items ALTER COLUMN cost_per_unit TYPE bigint USING round(cost_per_unit * 100)::bigint');

            // routes.cost – decimal dollars → integer cents
            DB::statement('ALTER TABLE routes ALTER COLUMN cost TYPE bigint USING round(cost * 100)::bigint');

            // products.unit_price – decimal dollars → integer cents
            DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE bigint USING round(unit_price * 100)::bigint');
            DB::statement('ALTER TABLE products ALTER COLUMN unit_price SET DEFAULT 0');

            // products.storage_cost – decimal dollars → integer cents
            DB::statement('ALTER TABLE products ALTER COLUMN storage_cost TYPE bigint USING round(storage_cost * 100)::bigint');
            DB::statement('ALTER TABLE products ALTER COLUMN storage_cost SET DEFAULT 0');

            // demand_events monetary columns – decimal dollars → integer cents
            DB::statement('ALTER TABLE demand_events ALTER COLUMN unit_price TYPE bigint USING round(unit_price * 100)::bigint');
            DB::statement('ALTER TABLE demand_events ALTER COLUMN unit_price SET DEFAULT 0');

            DB::statement('ALTER TABLE demand_events ALTER COLUMN revenue TYPE bigint USING round(revenue * 100)::bigint');
            DB::statement('ALTER TABLE demand_events ALTER COLUMN revenue SET DEFAULT 0');

            DB::statement('ALTER TABLE demand_events ALTER COLUMN lost_revenue TYPE bigint USING round(lost_revenue * 100)::bigint');
            DB::statement('ALTER TABLE demand_events ALTER COLUMN lost_revenue SET DEFAULT 0');

            // spike_events.resolution_cost – decimal dollars → integer cents
            DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE bigint USING round(COALESCE(resolution_cost, 0) * 100)::bigint');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('game_states', function (Blueprint $table) {
                $table->decimal('cash', 12, 2)->default(10000.00)->change();
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('total_cost', 12, 2)->change();
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('cost_per_unit', 12, 2)->change();
            });
            Schema::table('routes', function (Blueprint $table) {
                $table->decimal('cost', 12, 2)->change();
            });
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('unit_price', 12, 2)->default(0.00)->change();
                $table->decimal('storage_cost', 8, 2)->default(0.00)->change();
            });
            Schema::table('demand_events', function (Blueprint $table) {
                $table->decimal('unit_price', 12, 2)->change();
                $table->decimal('revenue', 12, 2)->change();
                $table->decimal('lost_revenue', 12, 2)->change();
            });
            Schema::table('spike_events', function (Blueprint $table) {
                $table->decimal('resolution_cost', 12, 2)->nullable()->change();
            });

        } else {
            // Revert to decimal(12,2) dollars
            DB::statement('ALTER TABLE game_states ALTER COLUMN cash TYPE numeric(12,2) USING cash::numeric(12,2)');
            DB::statement('ALTER TABLE game_states ALTER COLUMN cash SET DEFAULT 10000.00');

            DB::statement('ALTER TABLE orders ALTER COLUMN total_cost TYPE numeric(12,2) USING (total_cost / 100.0)::numeric(12,2)');
            DB::statement('ALTER TABLE order_items ALTER COLUMN cost_per_unit TYPE numeric(12,2) USING (cost_per_unit / 100.0)::numeric(12,2)');
            DB::statement('ALTER TABLE routes ALTER COLUMN cost TYPE numeric(12,2) USING (cost / 100.0)::numeric(12,2)');

            DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE numeric(12,2) USING (unit_price / 100.0)::numeric(12,2)');
            DB::statement('ALTER TABLE products ALTER COLUMN unit_price SET DEFAULT 0.00');

            DB::statement('ALTER TABLE products ALTER COLUMN storage_cost TYPE decimal(8,2) USING (storage_cost / 100.0)::decimal(8,2)');
            DB::statement('ALTER TABLE products ALTER COLUMN storage_cost SET DEFAULT 0.00');

            DB::statement('ALTER TABLE demand_events ALTER COLUMN unit_price TYPE numeric(12,2) USING (unit_price / 100.0)::numeric(12,2)');
            DB::statement('ALTER TABLE demand_events ALTER COLUMN revenue TYPE numeric(12,2) USING (revenue / 100.0)::numeric(12,2)');
            DB::statement('ALTER TABLE demand_events ALTER COLUMN lost_revenue TYPE numeric(12,2) USING (lost_revenue / 100.0)::numeric(12,2)');

            DB::statement('ALTER TABLE spike_events ALTER COLUMN resolution_cost TYPE numeric(12,2) USING (resolution_cost / 100.0)::numeric(12,2)');
        }
    }
};
