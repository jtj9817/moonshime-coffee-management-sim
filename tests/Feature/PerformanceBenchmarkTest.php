<?php

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('benchmarking dijkstra performance on 20+ nodes', function () {
    // --- 1. SETUP LARGE GRAPH ---
    // Create 25 locations
    $locations = Location::factory()->count(25)->create(['type' => 'warehouse']);
    
    // Create dense mesh network
    // Each location connects to 3-5 others
    foreach ($locations as $source) {
        $targets = $locations->where('id', '!=', $source->id)->random(rand(3, 5));
        
        foreach ($targets as $target) {
            Route::factory()->create([
                'source_id' => $source->id,
                'target_id' => $target->id,
                'cost' => rand(100, 1000),
                'is_active' => true,
            ]);
        }
    }

    $logistics = app(LogisticsService::class);
    $startNode = $locations->first();
    $endNode = $locations->last();

    // --- 2. MEASURE PERFORMANCE ---
    $start = microtime(true);
    
    // Run 100 pathfinding operations to get a good average
    for ($i = 0; $i < 100; $i++) {
        $path = $logistics->findBestRoute($startNode, $endNode);
    }
    
    $end = microtime(true);
    $totalTime = ($end - $start);
    $avgTime = $totalTime / 100;

    fwrite(STDERR, "\nDijkstra Performance Benchmark (25 nodes, 100 runs):\n");
    fwrite(STDERR, "Total Time: " . number_format($totalTime * 1000, 2) . "ms\n");
    fwrite(STDERR, "Avg Time per call: " . number_format($avgTime * 1000, 2) . "ms\n");

    // Assertion: Pathfinding should be fast (< 10ms avg)
    expect($avgTime)->toBeLessThan(0.010, "Pathfinding should take less than 10ms on average");
});
