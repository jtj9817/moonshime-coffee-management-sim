<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicateNames = DB::table('locations')
                ->select('name')
                ->groupBy('name')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('name');

            foreach ($duplicateNames as $name) {
                $locationIds = DB::table('locations')
                    ->where('name', $name)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                if (count($locationIds) < 2) {
                    continue;
                }

                $canonicalId = array_shift($locationIds);

                foreach ($locationIds as $duplicateId) {
                    $this->repointLocationReferences($duplicateId, $canonicalId);
                    DB::table('locations')->where('id', $duplicateId)->delete();
                }
            }

            $remainingDuplicates = DB::table('locations')
                ->select('name')
                ->groupBy('name')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($remainingDuplicates > 0) {
                throw new \RuntimeException('Unable to dedupe all duplicate location names before adding unique index.');
            }
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->unique('name', 'locations_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropUnique('locations_name_unique');
        });
    }

    private function repointLocationReferences(string $duplicateId, string $canonicalId): void
    {
        $this->mergeUserLocationConflicts($duplicateId, $canonicalId);
        $this->mergeInventoryConflicts($duplicateId, $canonicalId);
        $this->mergeInventoryHistoryConflicts($duplicateId, $canonicalId);
        $this->mergeLocationDailyMetricConflicts($duplicateId, $canonicalId);

        DB::table('alerts')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('demand_events')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('lost_sales')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('orders')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('scheduled_orders')->where('source_location_id', $duplicateId)->update(['source_location_id' => $canonicalId]);
        DB::table('scheduled_orders')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('shipments')->where('source_location_id', $duplicateId)->update(['source_location_id' => $canonicalId]);
        DB::table('shipments')->where('target_location_id', $duplicateId)->update(['target_location_id' => $canonicalId]);
        DB::table('spike_events')->where('location_id', $duplicateId)->update(['location_id' => $canonicalId]);
        DB::table('transfers')->where('source_location_id', $duplicateId)->update(['source_location_id' => $canonicalId]);
        DB::table('transfers')->where('target_location_id', $duplicateId)->update(['target_location_id' => $canonicalId]);

        $this->repointRouteReferences($duplicateId, $canonicalId);
    }

    private function mergeUserLocationConflicts(string $duplicateId, string $canonicalId): void
    {
        DB::statement(
            <<<'SQL'
            DELETE FROM user_locations ul_dup
            USING user_locations ul_keep
            WHERE ul_dup.location_id = ?
              AND ul_keep.location_id = ?
              AND ul_dup.user_id = ul_keep.user_id
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::table('user_locations')
            ->where('location_id', $duplicateId)
            ->update(['location_id' => $canonicalId]);
    }

    private function mergeInventoryConflicts(string $duplicateId, string $canonicalId): void
    {
        DB::statement(
            <<<'SQL'
            UPDATE inventories inv_keep
            SET quantity = inv_keep.quantity + inv_dup.quantity,
                last_restocked_at = COALESCE(
                    GREATEST(inv_keep.last_restocked_at, inv_dup.last_restocked_at),
                    inv_keep.last_restocked_at,
                    inv_dup.last_restocked_at
                ),
                updated_at = GREATEST(inv_keep.updated_at, inv_dup.updated_at)
            FROM inventories inv_dup
            WHERE inv_dup.location_id = ?
              AND inv_keep.location_id = ?
              AND inv_keep.product_id = inv_dup.product_id
              AND inv_keep.user_id IS NOT DISTINCT FROM inv_dup.user_id
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::statement(
            <<<'SQL'
            DELETE FROM inventories inv_dup
            USING inventories inv_keep
            WHERE inv_dup.location_id = ?
              AND inv_keep.location_id = ?
              AND inv_keep.product_id = inv_dup.product_id
              AND inv_keep.user_id IS NOT DISTINCT FROM inv_dup.user_id
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::table('inventories')
            ->where('location_id', $duplicateId)
            ->update(['location_id' => $canonicalId]);
    }

    private function mergeInventoryHistoryConflicts(string $duplicateId, string $canonicalId): void
    {
        DB::statement(
            <<<'SQL'
            UPDATE inventory_history ih_keep
            SET quantity = ih_keep.quantity + ih_dup.quantity,
                updated_at = GREATEST(ih_keep.updated_at, ih_dup.updated_at)
            FROM inventory_history ih_dup
            WHERE ih_dup.location_id = ?
              AND ih_keep.location_id = ?
              AND ih_keep.user_id = ih_dup.user_id
              AND ih_keep.product_id = ih_dup.product_id
              AND ih_keep.day = ih_dup.day
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::statement(
            <<<'SQL'
            DELETE FROM inventory_history ih_dup
            USING inventory_history ih_keep
            WHERE ih_dup.location_id = ?
              AND ih_keep.location_id = ?
              AND ih_keep.user_id = ih_dup.user_id
              AND ih_keep.product_id = ih_dup.product_id
              AND ih_keep.day = ih_dup.day
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::table('inventory_history')
            ->where('location_id', $duplicateId)
            ->update(['location_id' => $canonicalId]);
    }

    private function mergeLocationDailyMetricConflicts(string $duplicateId, string $canonicalId): void
    {
        DB::statement(
            <<<'SQL'
            UPDATE location_daily_metrics ldm_keep
            SET revenue = ldm_keep.revenue + ldm_dup.revenue,
                cogs = ldm_keep.cogs + ldm_dup.cogs,
                opex = ldm_keep.opex + ldm_dup.opex,
                net_profit = ldm_keep.net_profit + ldm_dup.net_profit,
                units_sold = ldm_keep.units_sold + ldm_dup.units_sold,
                stockouts = ldm_keep.stockouts + ldm_dup.stockouts,
                satisfaction = ROUND(((ldm_keep.satisfaction + ldm_dup.satisfaction) / 2.0)::numeric, 2),
                updated_at = GREATEST(ldm_keep.updated_at, ldm_dup.updated_at)
            FROM location_daily_metrics ldm_dup
            WHERE ldm_dup.location_id = ?
              AND ldm_keep.location_id = ?
              AND ldm_keep.user_id = ldm_dup.user_id
              AND ldm_keep.day = ldm_dup.day
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::statement(
            <<<'SQL'
            DELETE FROM location_daily_metrics ldm_dup
            USING location_daily_metrics ldm_keep
            WHERE ldm_dup.location_id = ?
              AND ldm_keep.location_id = ?
              AND ldm_keep.user_id = ldm_dup.user_id
              AND ldm_keep.day = ldm_dup.day
            SQL,
            [$duplicateId, $canonicalId]
        );

        DB::table('location_daily_metrics')
            ->where('location_id', $duplicateId)
            ->update(['location_id' => $canonicalId]);
    }

    private function repointRouteReferences(string $duplicateId, string $canonicalId): void
    {
        $this->collapseRouteConflictsBySource($duplicateId, $canonicalId);
        DB::table('routes')->where('source_id', $duplicateId)->update(['source_id' => $canonicalId]);

        $this->collapseRouteConflictsByTarget($duplicateId, $canonicalId);
        DB::table('routes')->where('target_id', $duplicateId)->update(['target_id' => $canonicalId]);
    }

    private function collapseRouteConflictsBySource(string $duplicateId, string $canonicalId): void
    {
        $conflicts = DB::table('routes as r_dup')
            ->join('routes as r_keep', function ($join) use ($duplicateId, $canonicalId): void {
                $join->on('r_dup.target_id', '=', 'r_keep.target_id')
                    ->on('r_dup.transport_mode', '=', 'r_keep.transport_mode')
                    ->where('r_dup.source_id', '=', $duplicateId)
                    ->where('r_keep.source_id', '=', $canonicalId);
            })
            ->select('r_dup.id as duplicate_route_id', 'r_keep.id as canonical_route_id')
            ->get();

        foreach ($conflicts as $conflict) {
            DB::table('shipments')
                ->where('route_id', $conflict->duplicate_route_id)
                ->update(['route_id' => $conflict->canonical_route_id]);

            DB::table('routes')
                ->where('id', $conflict->duplicate_route_id)
                ->delete();
        }
    }

    private function collapseRouteConflictsByTarget(string $duplicateId, string $canonicalId): void
    {
        $conflicts = DB::table('routes as r_dup')
            ->join('routes as r_keep', function ($join) use ($duplicateId, $canonicalId): void {
                $join->on('r_dup.source_id', '=', 'r_keep.source_id')
                    ->on('r_dup.transport_mode', '=', 'r_keep.transport_mode')
                    ->where('r_dup.target_id', '=', $duplicateId)
                    ->where('r_keep.target_id', '=', $canonicalId);
            })
            ->select('r_dup.id as duplicate_route_id', 'r_keep.id as canonical_route_id')
            ->get();

        foreach ($conflicts as $conflict) {
            DB::table('shipments')
                ->where('route_id', $conflict->duplicate_route_id)
                ->update(['route_id' => $conflict->canonical_route_id]);

            DB::table('routes')
                ->where('id', $conflict->duplicate_route_id)
                ->delete();
        }
    }
};
