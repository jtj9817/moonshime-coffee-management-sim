<?php

use App\Models\User;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('full disruption lifecycle integration', function () {
    $user = User::factory()->create();

    // Create GameState to prevent InitializeNewGame from auto-seeding spikes
    GameState::factory()->create(['user_id' => $user->id, 'day' => 2]);

    // 1. Setup Network: Hub A -> Cafe C (Direct), Hub A -> Hub B -> Cafe C (Alternative)
    $locA = Location::factory()->create(['name' => 'Hub A']);
    $locB = Location::factory()->create(['name' => 'Hub B']);
    $locC = Location::factory()->create(['name' => 'Cafe C']);

    $directRoute = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locC->id,
        'cost' => 100,
        'is_active' => true
    ]);

    Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'cost' => 50,
        'is_active' => true
    ]);

    Route::factory()->create([
        'source_id' => $locB->id,
        'target_id' => $locC->id,
        'cost' => 50,
        'is_active' => true
    ]);

    // 2. Trigger Blizzard (Block Direct Route)
    $directRoute->update(['is_active' => false]);
    SpikeEvent::factory()->create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'is_active' => true,
        'affected_route_id' => $directRoute->id,
        'magnitude' => 0.0
    ]);

    // 3. Verify Dashboard Metric Update (check we have at least our created spike)
    $response = $this->actingAs($user)
        ->get(route('game.dashboard'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->has('active_spikes_count')
        ->has('logistics_health')
    );
    
    $activeSpikesCount = $response->original->getData()['page']['props']['active_spikes_count'];
    expect($activeSpikesCount)->toBeGreaterThanOrEqual(1);

    // 4. Verify API Suggests Alternative
    $response = $this->actingAs($user)
        ->getJson(route('game.logistics.path', [
            'source_id' => $locA->id,
            'target_id' => $locC->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'reachable' => true,
            'total_cost' => 100 // 50 + 50
        ]);
    
    $data = $response->json();
    expect($data['path'])->toHaveCount(2); // Alpha -> Beta -> Gamma
});
