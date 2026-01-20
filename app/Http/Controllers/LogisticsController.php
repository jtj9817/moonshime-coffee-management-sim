<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    protected LogisticsService $logistics;

    public function __construct(LogisticsService $logistics)
    {
        $this->logistics = $logistics;
    }

    /**
     * Get all routes, optionally filtered by source or target.
     */
    /**
     * Get all routes, optionally filtered by source or target.
     */
    public function getRoutes(Request $request): JsonResponse
    {
        $query = Route::with(['source', 'target']);

        if ($request->has('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        if ($request->has('target_id')) {
            $query->where('target_id', $request->target_id);
        }

        $routes = $query->get();

        // Eager load active spikes for these routes to derive blocked_reason
        $activeSpikes = \App\Models\SpikeEvent::where('is_active', true)
            ->whereIn('affected_route_id', $routes->pluck('id'))
            ->get()
            ->keyBy('affected_route_id');

        $routesData = $routes->map(fn(Route $route) => [
            'id' => $route->id,
            'name' => ucfirst($route->transport_mode) . " Route",
            'source_id' => $route->source_id,
            'target_id' => $route->target_id,
            'source' => $route->source,
            'target' => $route->target,
            'transport_mode' => $route->transport_mode,
            'cost' => $this->logistics->calculateCost($route),
            'transit_days' => $route->transit_days,
            'capacity' => $route->capacity,
            'weather_vulnerability' => $route->weather_vulnerability,
            'is_active' => $route->is_active,
            'is_premium' => $this->logistics->isPremiumRoute($route),
            'blocked_reason' => $activeSpikes->has($route->id) ? $activeSpikes[$route->id]->type : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $routesData,
        ]);
    }

    /**
     * Get the best route between two locations.
     */
    public function getPath(Request $request): JsonResponse
    {
        $this->logistics->clearCache();

        $validated = $request->validate([
            'source_id' => 'required|exists:locations,id',
            'target_id' => 'required|exists:locations,id',
        ]);

        $source = Location::findOrFail($validated['source_id']);
        $target = Location::findOrFail($validated['target_id']);

        $path = $this->logistics->findBestRoute($source, $target);

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'No active routes found between these locations.',
                'reachable' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'reachable' => true,
            'path' => $path->map(fn($route) => [
                'id' => $route->id,
                'source' => $route->source->name,
                'target' => $route->target->name,
                'transport_mode' => $route->transport_mode,
                'cost' => $this->logistics->calculateCost($route),
                'transit_days' => $route->transit_days,
                'capacity' => $route->capacity,
                'is_premium' => $this->logistics->isPremiumRoute($route),
            ]),
            'total_cost' => round($path->sum(fn($r) => $this->logistics->calculateCost($r)), 2),
        ]);
    }

    /**
     * Get the logistics health metrics.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'health' => $this->logistics->getLogisticsHealth(),
        ]);
    }
}
