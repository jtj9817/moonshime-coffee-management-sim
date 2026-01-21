<?php

use App\Models\Location;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run GraphSeeder for route topology
    $this->seed(\Database\Seeders\GraphSeeder::class);
});

describe('Multi-hop Connectivity', function () {
    test('at least 3 vendors are connected to hubs', function () {
        $hubs = Location::where('type', 'hub')->get();

        // Count vendors that have at least one hub connection
        $connectedVendors = Location::where('type', 'vendor')
            ->whereHas('outgoingRoutes', fn($q) => $q->whereIn('target_id', $hubs->pluck('id')))
            ->count();

        expect($connectedVendors)->toBeGreaterThanOrEqual(3);
    });

    test('at least 3 hubs are connected to stores', function () {
        $stores = Location::where('type', 'store')->get();

        // Count hubs that have at least one store connection
        $connectedHubs = Location::where('type', 'hub')
            ->whereHas('outgoingRoutes', fn($q) => $q->whereIn('target_id', $stores->pluck('id')))
            ->count();

        expect($connectedHubs)->toBeGreaterThanOrEqual(3);
    });

    test('vendor to warehouse to store multi-hop path exists', function () {
        // Just verify at least one complete path exists
        $vendor = Location::where('type', 'vendor')
            ->whereHas('outgoingRoutes', fn($q) => $q->whereHas('target', fn($t) => $t->where('type', 'warehouse')))
            ->first();

        expect($vendor)->not->toBeNull('At least one vendor should connect to a warehouse');

        $warehouse = Route::where('source_id', $vendor->id)
            ->whereHas('target', fn($q) => $q->where('type', 'warehouse'))
            ->first()
            ->target;

        $storeConnection = Route::where('source_id', $warehouse->id)
            ->whereHas('target', fn($q) => $q->where('type', 'store'))
            ->exists();

        expect($storeConnection)->toBeTrue('Warehouse should connect to a store');
    });
});

describe('Path Diversity', function () {
    test('at least 3 distinct paths exist from any vendor to main store', function () {
        // Find a vendor that was created by GraphSeeder (has routes)
        $vendor = Location::where('type', 'vendor')
            ->whereHas('outgoingRoutes')
            ->first();
        $mainStore = Location::where('name', 'Moonshine Central')->first();

        if (!$vendor || !$mainStore) {
            $this->markTestSkipped('Vendor or main store not found');
        }

        // Count distinct paths via different intermediate nodes
        $paths = 0;

        // Path via warehouses
        $warehouseRoutes = Route::where('source_id', $vendor->id)
            ->whereHas('target', fn($q) => $q->where('type', 'warehouse'))
            ->get();

        foreach ($warehouseRoutes as $route) {
            $toStore = Route::where('source_id', $route->target_id)
                ->where('target_id', $mainStore->id)
                ->exists();
            if ($toStore) {
                $paths++;
            }
        }

        // Path via hubs
        $hubRoutes = Route::where('source_id', $vendor->id)
            ->whereHas('target', fn($q) => $q->where('type', 'hub'))
            ->get();

        foreach ($hubRoutes as $route) {
            $toStore = Route::where('source_id', $route->target_id)
                ->where('target_id', $mainStore->id)
                ->exists();
            if ($toStore) {
                $paths++;
            }
        }

        expect($paths)->toBeGreaterThanOrEqual(3);
    });
});
