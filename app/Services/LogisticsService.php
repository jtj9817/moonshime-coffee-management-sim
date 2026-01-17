<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

class LogisticsService
{
    /**
     * Get all active routes between two locations.
     *
     * @param Location $source
     * @param Location $target
     * @return Collection
     */
    public function getValidRoutes(Location $source, Location $target): Collection
    {
        return $source->outgoingRoutes() // Assuming relationship exists, or query directly
            ->where('target_id', $target->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Calculate the cost of traversing a route.
     *
     * @param \App\Models\Route $route
     * @return int
     */
    public function calculateCost(\App\Models\Route $route): int
    {
        return $route->weights['cost'] ?? 0;
    }

    /**
     * Check if a location is reachable from any supply source (Warehouse or Vendor) using active routes.
     * Uses Reverse-BFS.
     *
     * @param Location $target
     * @return bool
     */
    public function checkReachability(Location $target): bool
    {
        $queue = [$target];
        $visited = [$target->id => true];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // Check if we found a supply source
            if (in_array($current->type, ['warehouse', 'vendor'])) {
                return true;
            }

            // Get active incoming routes
            // Eager load source to avoid N+1 if traversing deep, but for BFS strictly logic:
            $incomingRoutes = $current->incomingRoutes()->where('is_active', true)->with('source')->get();

            foreach ($incomingRoutes as $route) {
                $source = $route->source;
                if ($source && !isset($visited[$source->id])) {
                    $visited[$source->id] = true;
                    $queue[] = $source;
                }
            }
        }

        return false;
    }
}
