<?php

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkReachability returns true if supply source is reachable', function () {
    // A -> B -> C (Target)
    // A is Warehouse (Supply Source)
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $nodeB = Location::factory()->create(['type' => 'hub']);
    $target = Location::factory()->create(['type' => 'store']);

    Route::factory()->create(['source_id' => $warehouse->id, 'target_id' => $nodeB->id, 'is_active' => true]);
    Route::factory()->create(['source_id' => $nodeB->id, 'target_id' => $target->id, 'is_active' => true]);

    $service = new LogisticsService();
    
    // Reverse BFS from Target should find Warehouse
    expect($service->checkReachability($target))->toBeTrue();
});

test('checkReachability returns false if isolated from supply', function () {
    // A (Warehouse)   C (Target)
    // No path
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $target = Location::factory()->create(['type' => 'store']);

    $service = new LogisticsService();
    
    expect($service->checkReachability($target))->toBeFalse();
});

test('checkReachability returns false if path is inactive', function () {
    // A -> B (inactive) -> C
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $nodeB = Location::factory()->create(['type' => 'hub']);
    $target = Location::factory()->create(['type' => 'store']);

    Route::factory()->create(['source_id' => $warehouse->id, 'target_id' => $nodeB->id, 'is_active' => false]);
    Route::factory()->create(['source_id' => $nodeB->id, 'target_id' => $target->id, 'is_active' => true]);

    $service = new LogisticsService();
    
    expect($service->checkReachability($target))->toBeFalse();
});
