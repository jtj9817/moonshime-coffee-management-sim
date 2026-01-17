<?php

use App\Models\Location;
use App\Models\Route;
use Database\Seeders\GraphSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('graph seeder creates expected topology', function () {
    $this->seed(GraphSeeder::class);

    // Verify Locations
    expect(Location::where('type', 'vendor')->count())->toBe(3);
    expect(Location::where('type', 'warehouse')->count())->toBe(2);
    expect(Location::where('type', 'store')->count())->toBe(5);
    expect(Location::where('type', 'hub')->count())->toBe(1);
    expect(Location::count())->toBe(11);

    // Verify Routes
    // Vendors -> Warehouses: 3 * 2 = 6
    // Warehouses -> Stores: 2 * 5 = 10
    // Store -> Store: 4
    // Vendors -> Hub: 3
    // Hub -> Stores: 5
    // Total: 28
    expect(Route::count())->toBe(28);

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
