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
    expect(Location::where('type', 'hub')->count())->toBeGreaterThanOrEqual(1);
    
    // Verify Routes
    expect(Route::count())->toBeGreaterThanOrEqual(28);

    // Verify specific connections exist
    $vendor = Location::where('type', 'vendor')->first();
    $warehouse = Location::where('type', 'warehouse')->first();
    
    // Check if there is at least one route from a vendor to a warehouse
    // Note: Since we connect ALL vendors to ALL warehouses, this must exist.
    $route = Route::where('source_id', $vendor->id)
                  ->where('target_id', $warehouse->id)
                  ->first();
    
    expect($route)->not->toBeNull();
    expect($route->transport_mode)->toBe('Truck');
});
