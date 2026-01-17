<?php

use App\Models\Route;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('route model has expected attributes', function () {
    $route = Route::factory()->create([
        'transport_mode' => 'Truck',
        'is_active' => true,
        'weights' => ['cost' => 10, 'time' => 5],
    ]);

    expect($route)->toBeInstanceOf(Route::class);
    expect($route->transport_mode)->toBe('Truck');
    expect($route->is_active)->toBeTrue();
    expect($route->weights)->toBeArray();
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
