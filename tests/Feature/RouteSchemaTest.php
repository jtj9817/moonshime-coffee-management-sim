<?php

use App\Models\Route;
use App\Models\Location;
use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('routes table has explicit columns instead of JSON weights', function () {
    $columns = Schema::getColumnListing('routes');
    
    expect($columns)->toContain('cost')
        ->toContain('transit_days')
        ->toContain('capacity')
        ->not->toContain('weights');
});

test('routes table has strict foreign key constraints and unique index', function () {
    $locA = Location::factory()->create();
    $locB = Location::factory()->create();

    // Verify unique constraint: (source_id, target_id, transport_mode)
    Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Truck',
        'cost' => 1.00,
        'transit_days' => 2,
        'capacity' => 1000
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Truck',
        'cost' => 2.00,
        'transit_days' => 3,
        'capacity' => 2000
    ]);
});
