<?php

use App\Models\User;
use App\Models\SpikeEvent;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard includes explicit logistics metrics props', function () {
    // Arrange: Create some active spike events
    SpikeEvent::factory()->count(3)->create(['is_active' => true]);
    SpikeEvent::factory()->count(2)->create(['is_active' => false]);

    $user = User::factory()->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('game.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('logistics_health')
            ->has('active_spikes_count')
            ->where('active_spikes_count', 3)
        );
});
