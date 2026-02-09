<?php

use App\Models\GameState;
use App\Models\Quest;
use App\Models\User;
use App\QuestTriggers\OrdersPlacedTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the quests page for authenticated users', function () {
    $user = User::factory()->create();
    GameState::factory()->create(['user_id' => $user->id]);

    Quest::factory()->create([
        'title' => 'Test Quest',
        'trigger_class' => OrdersPlacedTrigger::class,
        'target_value' => 3,
    ]);

    $response = $this->actingAs($user)->get('/game/quests');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('game/quests')
        ->has('quests', 1)
        ->where('quests.0.title', 'Test Quest')
    );
});

it('returns empty quests when none exist', function () {
    $user = User::factory()->create();
    GameState::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/game/quests');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('game/quests')
        ->has('quests', 0)
    );
});

it('requires authentication', function () {
    $response = $this->get('/game/quests');
    $response->assertRedirect();
});
