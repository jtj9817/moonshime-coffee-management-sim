<?php

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dijkstra average time is within budget', function () {
    $service = app(LogisticsService::class);

    // Setup 5x5 grid
    $gridSize = 5;
    $nodes = [];
    
    for ($y = 0; $y < $gridSize; $y++) {
        for ($x = 0; $x < $gridSize; $x++) {
            $nodes[$x][$y] = Location::factory()->create([
                'name' => "Node_{$x}_{$y}",
                'address' => "Addr_{$x}_{$y}",
            ]);
        }
    }
    
    for ($y = 0; $y < $gridSize; $y++) {
        for ($x = 0; $x < $gridSize; $x++) {
            if ($x < $gridSize - 1) {
                Route::factory()->create([
                    'source_id' => $nodes[$x][$y]->id,
                    'target_id' => $nodes[$x+1][$y]->id,
                    'is_active' => true,
                    'cost' => rand(10, 50),
                ]);
            }
            if ($y < $gridSize - 1) {
                Route::factory()->create([
                    'source_id' => $nodes[$x][$y]->id,
                    'target_id' => $nodes[$x][$y+1]->id,
                    'is_active' => true,
                    'cost' => rand(10, 50),
                ]);
            }
        }
    }

    $source = $nodes[0][0];
    $target = $nodes[4][4];

    $start = microtime(true);
    $iterations = 5;
    
    for ($i = 0; $i < $iterations; $i++) {
        $path = $service->findBestRoute($source, $target);
    }
    
    $end = microtime(true);
    $avgTimeMs = (($end - $start) / $iterations) * 1000;
    
    // Log for visibility
    fwrite(STDERR, "\nAverage Dijkstra Time: " . number_format($avgTimeMs, 4) . "ms\n");

    expect($path)->not->toBeNull();
    expect($avgTimeMs)->toBeLessThan(10.0, "Dijkstra average time ($avgTimeMs ms) exceeds budget of 10ms");
});

test('optimized dijkstra respects active spikes', function () {
    $service = app(LogisticsService::class);

    $source = Location::factory()->create(['name' => 'Source', 'address' => 'A']);
    $target = Location::factory()->create(['name' => 'Target', 'address' => 'B']);

    // Route A: Base cost 100, no spike
    $routeA = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'cost' => 100,
        'transport_mode' => 'truck_A',
        'is_active' => true,
    ]);

    // Route B: Base cost 50, but with a 2.0 spike (+200%) -> Effective 150
    $routeB = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'cost' => 50,
        'transport_mode' => 'truck_B',
        'is_active' => true,
    ]);

    \App\Models\SpikeEvent::factory()->create([
        'affected_route_id' => $routeB->id,
        'magnitude' => 2.0,
        'is_active' => true,
    ]);

    // Clear service cache to pick up new spike
    $service->clearCache();

    $path = $service->findBestRoute($source, $target);

    expect($path)->not->toBeNull();
    expect($path)->toHaveCount(1);
    // Should choose Route A because 100 < 150
    expect($path->first()->id)->toBe($routeA->id);
    expect($path->first()->cost)->toBe(100);
});