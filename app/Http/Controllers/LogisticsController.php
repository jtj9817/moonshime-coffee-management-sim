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
    public function getRoutes(Request $request): JsonResponse
    {
        $query = Route::with(['source', 'target']);

        if ($request->has('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        if ($request->has('target_id')) {
            $query->where('target_id', $request->target_id);
        }

        $routes = $query->get()->map(fn(Route $route) => [
            'id' => $route->id,
            'source_id' => $route->source_id,
            'target_id' => $route->target_id,
            'source' => $route->source,
            'target' => $route->target,
            'transport_mode' => $route->transport_mode,
            'cost' => $route->cost,
            'transit_days' => $route->transit_days,
            'is_active' => $route->is_active,
        ]);

        return response()->json(['data' => $routes]);
    }

    /**
     * Get the best route between two locations.
     */
    public function getPath(Request $request): JsonResponse
    {
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
                'is_premium' => $this->logistics->isPremiumRoute($route),
            ]),
            'total_cost' => $path->sum(fn($r) => $this->logistics->calculateCost($r)),
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
