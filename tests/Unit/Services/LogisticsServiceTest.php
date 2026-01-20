<?php

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getValidRoutes returns only active routes between source and target', function () {
    $source = Location::factory()->create();
    $target = Location::factory()->create();
    
    // Create active route
    $activeRoute = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'transport_mode' => 'Truck',
        'is_active' => true,
    ]);

    // Create inactive route (using different transport mode to avoid unique constraint)
    Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'transport_mode' => 'Air',
        'is_active' => false,
    ]);

    // Create route for different locations
    Route::factory()->create([
        'source_id' => Location::factory()->create()->id,
        'target_id' => $target->id,
        'is_active' => true,
    ]);

    $service = new LogisticsService();
    $routes = $service->getValidRoutes($source, $target);

    expect($routes)->toHaveCount(1);
    expect($routes->first()->id)->toBe($activeRoute->id);
});

test('calculateCost returns correct cost from route base cost', function () {
    $route = Route::factory()->create([
        'cost' => 1.50,
    ]);

    $service = new LogisticsService();
    $cost = $service->calculateCost($route);

    expect($cost)->toBe(1.50);
});
