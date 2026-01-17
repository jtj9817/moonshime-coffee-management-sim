<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

class LogisticsService
{
    /**
     * Get the overall logistics health (percentage of active routes).
     *
     * @return float
     */
    public function getLogisticsHealth(): float
    {
        $totalRoutes = \App\Models\Route::count();
        if ($totalRoutes === 0) {
            return 100.0;
        }

        $activeRoutes = \App\Models\Route::where('is_active', true)->count();

        return ($activeRoutes / $totalRoutes) * 100;
    }

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
     * Calculate the cost of traversing a route, including spike effects.
     *
     * @param \App\Models\Route $route
     * @return int
     */
    public function calculateCost(\App\Models\Route $route): int
    {
        $baseCost = $route->cost;

        // Check for active spikes affecting this specific route
        $spikeMultiplier = \App\Models\SpikeEvent::where('affected_route_id', $route->id)
            ->where('is_active', true)
            ->sum('magnitude');

        if ($spikeMultiplier > 0) {
            return (int) ($baseCost * (1 + $spikeMultiplier));
        }

        return $baseCost;
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

    /**
     * Find the best route (cheapest path) between two locations using Dijkstra's algorithm.
     *
     * @param Location $source
     * @param Location $target
     * @return Collection|null Collection of Route objects or null if no path
     */
    public function findBestRoute(Location $source, Location $target): ?Collection
    {
        $distances = [];
        $previous = [];
        $routeUsed = []; // To store the specific Route object used to reach a node
        $queue = new \SplPriorityQueue();

        // Init
        // We use string IDs, so we need a map.
        // Since we don't know all nodes upfront easily without scanning DB,
        // we'll initialize on demand or just use array map.
        
        $distances[$source->id] = 0;
        $queue->insert($source, 0);

        while (!$queue->isEmpty()) {
            $current = $queue->extract();

            if ($current->id === $target->id) {
                break; // Found target
            }

            // Get outgoing routes
            // Eager load target to ensure we have the node object for the queue
            $outgoing = $current->outgoingRoutes()->where('is_active', true)->with('target')->get();

            foreach ($outgoing as $route) {
                $neighbor = $route->target;
                if (!$neighbor) continue;

                $alt = $distances[$current->id] + $this->calculateCost($route);

                if (!isset($distances[$neighbor->id]) || $alt < $distances[$neighbor->id]) {
                    $distances[$neighbor->id] = $alt;
                    $previous[$neighbor->id] = $current;
                    $routeUsed[$neighbor->id] = $route;
                    // Priority Queue is Max-Heap, so use negative priority for Min-Heap behavior
                    $queue->insert($neighbor, -$alt);
                }
            }
        }

        if (!isset($distances[$target->id])) {
            return null; // unreachable
        }

        return $this->reconstructPath($previous, $routeUsed, $target->id);
    }

    /**
     * Reconstruct path
     *
     * @param array $previous
     * @param array $routeUsed
     * @param string $targetId
     * @return Collection
     */
    protected function reconstructPath(array $previous, array $routeUsed, string $targetId): Collection
    {
        $path = new Collection();
        $currId = $targetId;

        while (isset($previous[$currId])) {
            $route = $routeUsed[$currId];
            $path->prepend($route);
            $currId = $previous[$currId]->id;
        }

        return $path;
    }

    /**
     * Determine if a route is considered "premium" (e.g., an expensive alternative).
     *
     * @param \App\Models\Route $route
     * @return bool
     */
    public function isPremiumRoute(\App\Models\Route $route): bool
    {
        $premiumModes = ['air', 'courier', 'express'];
        if (in_array(strtolower($route->transport_mode), $premiumModes)) {
            return true;
        }

        // Compare against other active routes between the same locations
        $cheapestBaseRoute = \App\Models\Route::where('source_id', $route->source_id)
            ->where('target_id', $route->target_id)
            ->where('is_active', true)
            ->orderBy('cost', 'asc')
            ->first();

        if ($cheapestBaseRoute && $cheapestBaseRoute->id !== $route->id) {
            $currentBaseCost = $route->cost;
            $minBaseCost = $cheapestBaseRoute->cost;

            if ($currentBaseCost > $minBaseCost) {
                return true;
            }
        }

        return false;
    }
}
