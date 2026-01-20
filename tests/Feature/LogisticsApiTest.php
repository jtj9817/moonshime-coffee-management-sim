<?php

use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('getPath returns optimal path and cost', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create(['name' => 'Hub A']);
    $locB = Location::factory()->create(['name' => 'Cafe B']);

    $route = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'truck',
        'cost' => 1.00,
        'is_active' => true
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locB->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'reachable' => true,
            'total_cost' => 1.00
        ]);
});

test('getPath returns 404/error when no path exists', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create();
    $locB = Location::factory()->create();

    // No route created

    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locB->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => false,
            'reachable' => false
        ]);
});

test('getPath reflects cost increases from active spikes', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create();
    $locB = Location::factory()->create();

    $route = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'truck',
        'cost' => 1.00,
        'is_active' => true
    ]);

    // Create a spike that affects this route
    SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'is_active' => true,
        'affected_route_id' => $route->id,
        'magnitude' => 0.5 // 50% increase
    ]);
    
    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locB->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'total_cost' => 1.50
        ]);
});
