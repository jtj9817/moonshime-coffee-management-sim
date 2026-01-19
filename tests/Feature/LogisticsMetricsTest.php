<?php

use App\Models\User;
use App\Models\GameState;
use App\Models\SpikeEvent;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard includes explicit logistics metrics props', function () {
    $user = User::factory()->create();

    // Create GameState to prevent InitializeNewGame from auto-seeding spikes
    GameState::factory()->create(['user_id' => $user->id, 'day' => 2]);

    // Arrange: Create some active spike events
    SpikeEvent::factory()->count(3)->create(['is_active' => true, 'user_id' => $user->id]);
    SpikeEvent::factory()->count(2)->create(['is_active' => false, 'user_id' => $user->id]);

    // Act & Assert (check we have at least our created active spikes)
    $response = $this->actingAs($user)
        ->get(route('game.dashboard'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->has('logistics_health')
        ->has('active_spikes_count')
    );
    
    $activeSpikesCount = $response->original->getData()['page']['props']['active_spikes_count'];
    expect($activeSpikesCount)->toBeGreaterThanOrEqual(3);
});
