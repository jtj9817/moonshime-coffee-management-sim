<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LogisticsService
{
    /**
     * In-memory cache for the graph structure to avoid redundant DB queries during a single request/tick.
     */
    protected ?array $graphCache = null;

    /**
     * Optional user ID for scoping spike events.
     * When set, only spikes belonging to this user affect route costs.
     */
    protected ?int $userId = null;

    /**
     * Set the user context for spike-scoped calculations.
     */
    public function forUser(?int $userId): static
    {
        $this->userId = $userId;
        $this->graphCache = null; // Invalidate cache when user changes
        return $this;
    }

    /**
     * Get the current user ID, falling back to auth().
     */
    protected function resolveUserId(): ?int
    {
        return $this->userId ?? auth()->id();
    }

    /**
     * Clear the in-memory graph cache.
     */
    public function clearCache(): void
    {
        $this->graphCache = null;
    }

    /**
     * Get the overall logistics health (percentage of active routes).
     *
     * @return float
     */
    public function getLogisticsHealth(): float
    {
        $totalRoutes = Route::count();
        if ($totalRoutes === 0) {
            return 100.0;
        }

        $activeRoutes = Route::where('is_active', true)->count();

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
        return Route::where('source_id', $source->id)
            ->where('target_id', $target->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Calculate the cost of traversing a route, including spike effects.
     *
     * @param Route $route
     * @return float
     */
    public function calculateCost(Route $route): int
    {
        $baseCost = (int) $route->cost;

        // Check for active spikes affecting this specific route (user-scoped)
        $query = SpikeEvent::where('affected_route_id', $route->id)
            ->where('is_active', true);
        $userId = $this->resolveUserId();
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $spikeMultiplier = $query->sum('magnitude');

        if ($spikeMultiplier > 0) {
            return (int) round($baseCost * (1 + $spikeMultiplier));
        }

        return $baseCost;
    }

    /**
     * Build an adjacency list of the graph for efficient traversal.
     * Pre-calculates costs including active spikes.
     *
     * @return array
     */
    protected function getAdjacencyList(): array
    {
        if ($this->graphCache !== null) {
            return $this->graphCache;
        }

        // 1. Get all active spikes affecting routes (user-scoped)
        $spikeQuery = SpikeEvent::where('is_active', true)
            ->whereNotNull('affected_route_id');
        $userId = $this->resolveUserId();
        if ($userId !== null) {
            $spikeQuery->where('user_id', $userId);
        }
        $routeSpikes = $spikeQuery
            ->select('affected_route_id')
            ->selectRaw('SUM(magnitude) as total_magnitude')
            ->groupBy('affected_route_id')
            ->pluck('total_magnitude', 'affected_route_id')
            ->toArray();

        // 2. Load all active routes with targets
        $routes = Route::where('is_active', true)->with('target')->get();

        $adj = [];
        foreach ($routes as $route) {
            $magnitude = $routeSpikes[$route->id] ?? 0;
            $effectiveCost = round(((float) $route->cost) * (1 + $magnitude), 2);

            $adj[$route->source_id][] = [
                'route' => $route,
                'target' => $route->target,
                'cost' => $effectiveCost,
            ];
        }

        $this->graphCache = $adj;
        return $adj;
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
        // For reverse BFS, we need an adjacency list of INCOMING routes
        // Since getAdjacencyList is optimized for outgoing (Dijkstra), 
        // we'll do a quick specialized version here or keep existing logic if it's not a hotspot.
        // Given we want performance, let's optimize it too.
        
        $allActiveRoutes = Route::where('is_active', true)->with('source')->get();
        $incomingAdj = [];
        foreach ($allActiveRoutes as $route) {
            $incomingAdj[$route->target_id][] = $route->source;
        }

        $queue = [$target];
        $visited = [$target->id => true];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // Check if we found a supply source
            if (in_array($current->type, ['warehouse', 'vendor'])) {
                return true;
            }

            $sources = $incomingAdj[$current->id] ?? [];
            foreach ($sources as $source) {
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
        $adj = $this->getAdjacencyList();
        
        $distances = [];
        $previous = [];
        $routeUsed = []; 
        $queue = new \SplPriorityQueue();

        $distances[$source->id] = 0;
        $queue->insert($source, 0);

        while (!$queue->isEmpty()) {
            $current = $queue->extract();

            if ($current->id === $target->id) {
                break; // Found target
            }

            // Use cached adjacency list
            $outgoing = $adj[$current->id] ?? [];

            foreach ($outgoing as $edge) {
                $neighbor = $edge['target'];
                $cost = $edge['cost'];
                
                if (!$neighbor) continue;

                $alt = $distances[$current->id] + $cost;

                if (!isset($distances[$neighbor->id]) || $alt < $distances[$neighbor->id]) {
                    $distances[$neighbor->id] = $alt;
                    $previous[$neighbor->id] = $current;
                    $routeUsed[$neighbor->id] = $edge['route'];
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
     * @param Route $route
     * @return bool
     */
    public function isPremiumRoute(Route $route): bool
    {
        $premiumModes = ['air', 'courier', 'express'];
        if (in_array(strtolower($route->transport_mode), $premiumModes)) {
            return true;
        }

        // Compare against other active routes between the same locations
        $cheapestBaseRoute = Route::where('source_id', $route->source_id)
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
