<?php

use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('getPath marks expensive alternative routes as premium', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create();
    $locB = Location::factory()->create();

    // Standard cheap route
    $standard = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Truck',
        'cost' => 100,
        'is_active' => true
    ]);

    // Premium expensive route
    $premium = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Air',
        'cost' => 500,
        'is_active' => true
    ]);

    // Case 1: Standard route is active and cheapest.
    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locB->id,
        ]));

    $response->assertOk()
        ->assertJsonPath('path.0.id', $standard->id)
        ->assertJsonPath('path.0.is_premium', false);

    // Case 2: Spike hits standard route, making premium route the best choice.
    SpikeEvent::factory()->create([
        'type' => 'strike',
        'is_active' => true,
        'affected_route_id' => $standard->id,
        'magnitude' => 10.0 // Cost becomes 1100
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locB->id,
        ]));

    $response->assertOk()
        ->assertJsonPath('path.0.id', $premium->id)
        ->assertJsonPath('path.0.is_premium', true);
});
