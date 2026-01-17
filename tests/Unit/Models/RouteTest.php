<?php

use App\Models\Route;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('route model has expected attributes', function () {
    $route = Route::factory()->create([
        'transport_mode' => 'Truck',
        'cost' => 100,
        'transit_days' => 2,
        'capacity' => 1000,
        'is_active' => true,
        'weather_vulnerability' => true
    ]);

    expect($route->transport_mode)->toBe('Truck');
    expect($route->cost)->toBe(100);
    expect($route->transit_days)->toBe(2);
    expect($route->capacity)->toBe(1000);
    expect($route->is_active)->toBeTrue();
    expect($route->weather_vulnerability)->toBeTrue();
});

test('route belongs to source and target locations', function () {
    $source = Location::factory()->create();
    $target = Location::factory()->create();

    $route = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
    ]);

    expect($route->source)->toBeInstanceOf(Location::class);
    expect($route->source->id)->toBe($source->id);

    expect($route->target)->toBeInstanceOf(Location::class);
    expect($route->target->id)->toBe($target->id);
});
