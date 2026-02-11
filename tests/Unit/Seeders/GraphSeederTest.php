<?php

use App\Models\Location;
use App\Models\Route;
use Database\Seeders\GraphSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('graph seeder creates expected topology', function () {
    $this->seed(GraphSeeder::class);

    // Verify Locations
    expect(Location::where('type', 'vendor')->count())->toBeGreaterThanOrEqual(3);
    expect(Location::where('type', 'warehouse')->count())->toBeGreaterThanOrEqual(2);
    expect(Location::where('type', 'store')->count())->toBeGreaterThanOrEqual(5);
    expect(Location::where('type', 'hub')->count())->toBeGreaterThanOrEqual(3); // Now 3 hubs

    // Verify Routes - increased with 3 hubs
    // 3 vendors × 2 warehouses = 6 (vendor→warehouse)
    // 2 warehouses × 6 stores = 12 (warehouse→store)
    // 5 stores chained = 5 (store→store)
    // 3 vendors × 3 hubs = 9 (vendor→hub)
    // 3 hubs × 6 stores = 18 (hub→store)
    // Total: 50+ routes
    expect(Route::count())->toBeGreaterThanOrEqual(40);

    // Verify multi-hop connectivity exists - at least one vendor connected to a hub via Air
    $vendorToHubRoute = Route::where('transport_mode', 'Air')
        ->whereHas('source', fn ($q) => $q->where('type', 'vendor'))
        ->whereHas('target', fn ($q) => $q->where('type', 'hub'))
        ->first();

    expect($vendorToHubRoute)->not->toBeNull('At least one vendor should be connected to a hub via Air');
    expect($vendorToHubRoute->transport_mode)->toBe('Air');
});

test('graph seeder is idempotent for locations and routes', function () {
    $this->seed(GraphSeeder::class);

    $initialLocationCount = Location::count();
    $initialRouteCount = Route::count();

    $this->seed(GraphSeeder::class);

    $currentLocationCount = Location::count();
    $uniqueLocationNames = Location::pluck('name')->unique()->count();

    expect($currentLocationCount)->toBe($initialLocationCount);
    expect($uniqueLocationNames)->toBe($currentLocationCount);
    expect(Location::where('name', 'Moonshine Central')->count())->toBe(1);
    expect(Route::count())->toBe($initialRouteCount);
});
