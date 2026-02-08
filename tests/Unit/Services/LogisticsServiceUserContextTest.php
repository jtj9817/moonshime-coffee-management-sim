<?php

use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forUser scopes route cost calculations by spike owner', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $source = Location::factory()->create();
    $target = Location::factory()->create();

    $route = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'transport_mode' => 'Truck',
        'cost' => 100,
        'is_active' => true,
    ]);

    SpikeEvent::factory()->create([
        'user_id' => $userA->id,
        'is_active' => true,
        'affected_route_id' => $route->id,
        'magnitude' => 0.25,
    ]);

    SpikeEvent::factory()->create([
        'user_id' => $userB->id,
        'is_active' => true,
        'affected_route_id' => $route->id,
        'magnitude' => 0.75,
    ]);

    $service = app(LogisticsService::class);

    expect($service->forUser($userA->id)->calculateCost($route))->toBe(125);
    expect($service->forUser($userB->id)->calculateCost($route))->toBe(175);
});

test('forUser invalidates cached graph so best path is recalculated per user context', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $a = Location::factory()->create(['name' => 'A']);
    $b = Location::factory()->create(['name' => 'B']);
    $c = Location::factory()->create(['name' => 'C']);

    $direct = Route::factory()->create([
        'source_id' => $a->id,
        'target_id' => $b->id,
        'transport_mode' => 'Truck',
        'cost' => 100,
        'is_active' => true,
    ]);

    $viaOne = Route::factory()->create([
        'source_id' => $a->id,
        'target_id' => $c->id,
        'transport_mode' => 'Air',
        'cost' => 40,
        'is_active' => true,
    ]);

    $viaTwo = Route::factory()->create([
        'source_id' => $c->id,
        'target_id' => $b->id,
        'transport_mode' => 'Ship',
        'cost' => 40,
        'is_active' => true,
    ]);

    SpikeEvent::factory()->create([
        'user_id' => $userA->id,
        'is_active' => true,
        'affected_route_id' => $viaOne->id,
        'magnitude' => 2.0,
    ]);

    SpikeEvent::factory()->create([
        'user_id' => $userA->id,
        'is_active' => true,
        'affected_route_id' => $viaTwo->id,
        'magnitude' => 2.0,
    ]);

    $service = app(LogisticsService::class);

    $pathForUserA = $service->forUser($userA->id)->findBestRoute($a, $b);
    $pathForUserB = $service->forUser($userB->id)->findBestRoute($a, $b);

    expect($pathForUserA)->not()->toBeNull()
        ->and($pathForUserA->pluck('id')->all())->toBe([$direct->id]);

    expect($pathForUserB)->not()->toBeNull()
        ->and($pathForUserB->pluck('id')->all())->toBe([$viaOne->id, $viaTwo->id]);
});
