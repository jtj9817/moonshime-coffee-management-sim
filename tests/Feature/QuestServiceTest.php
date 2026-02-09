<?php

use App\Models\GameState;
use App\Models\Order;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuest;
use App\QuestTriggers\DaysPlayedTrigger;
use App\QuestTriggers\OrdersPlacedTrigger;
use App\Services\QuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('checks triggers and completes a quest when target is met', function () {
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 1000000,
        'xp' => 0,
        'day' => 5,
    ]);

    $quest = Quest::factory()->create([
        'type' => 'orders_placed',
        'title' => 'Place 3 Orders',
        'target_value' => 3,
        'reward_cash_cents' => 50000,
        'reward_xp' => 100,
        'trigger_class' => OrdersPlacedTrigger::class,
    ]);

    // Create 3 orders for the user
    Order::factory()->count(3)->create(['user_id' => $user->id]);

    $service = app(QuestService::class);
    $result = $service->checkTriggers($user);

    expect($result['completed'])->toHaveCount(1);
    expect($result['completed'][0]['quest_id'])->toBe($quest->id);

    // Verify rewards granted
    $gameState->refresh();
    expect($gameState->cash)->toBe(1050000);
    expect($gameState->xp)->toBe(100);

    // Verify user quest marked complete
    $userQuest = UserQuest::where('user_id', $user->id)->where('quest_id', $quest->id)->first();
    expect($userQuest->is_completed)->toBeTrue();
    expect($userQuest->completed_at)->not->toBeNull();
});

it('does not complete quest when target is not met', function () {
    $user = User::factory()->create();
    GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 1000000,
        'xp' => 0,
        'day' => 1,
    ]);

    Quest::factory()->create([
        'type' => 'orders_placed',
        'title' => 'Place 5 Orders',
        'target_value' => 5,
        'trigger_class' => OrdersPlacedTrigger::class,
    ]);

    // Only 2 orders
    Order::factory()->count(2)->create(['user_id' => $user->id]);

    $service = app(QuestService::class);
    $result = $service->checkTriggers($user);

    expect($result['completed'])->toBeEmpty();

    // Progress should be updated
    $userQuest = UserQuest::where('user_id', $user->id)->first();
    expect($userQuest->current_value)->toBe(2);
    expect($userQuest->is_completed)->toBeFalse();
});

it('does not re-complete an already completed quest', function () {
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 1000000,
        'xp' => 0,
        'day' => 5,
    ]);

    $quest = Quest::factory()->create([
        'type' => 'orders_placed',
        'target_value' => 1,
        'reward_cash_cents' => 50000,
        'reward_xp' => 100,
        'trigger_class' => OrdersPlacedTrigger::class,
    ]);

    Order::factory()->create(['user_id' => $user->id]);

    $service = app(QuestService::class);

    // First check: completes
    $result1 = $service->checkTriggers($user);
    expect($result1['completed'])->toHaveCount(1);

    // Second check: should not re-complete
    $result2 = $service->checkTriggers($user);
    expect($result2['completed'])->toBeEmpty();

    // Cash should only be incremented once
    $gameState->refresh();
    expect($gameState->cash)->toBe(1050000);
});

it('uses DaysPlayedTrigger correctly', function () {
    $user = User::factory()->create();
    GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 1000000,
        'xp' => 0,
        'day' => 7,
    ]);

    Quest::factory()->create([
        'type' => 'days_played',
        'target_value' => 5,
        'reward_cash_cents' => 25000,
        'reward_xp' => 50,
        'trigger_class' => DaysPlayedTrigger::class,
    ]);

    $service = app(QuestService::class);
    $result = $service->checkTriggers($user);

    expect($result['completed'])->toHaveCount(1);
});

it('isolates quest progress per user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    GameState::factory()->create(['user_id' => $user1->id, 'cash' => 1000000, 'xp' => 0, 'day' => 1]);
    GameState::factory()->create(['user_id' => $user2->id, 'cash' => 1000000, 'xp' => 0, 'day' => 1]);

    $quest = Quest::factory()->create([
        'type' => 'orders_placed',
        'target_value' => 2,
        'reward_cash_cents' => 10000,
        'reward_xp' => 50,
        'trigger_class' => OrdersPlacedTrigger::class,
    ]);

    // User1 has 3 orders, user2 has 1
    Order::factory()->count(3)->create(['user_id' => $user1->id]);
    Order::factory()->count(1)->create(['user_id' => $user2->id]);

    $service = app(QuestService::class);

    $result1 = $service->checkTriggers($user1);
    $result2 = $service->checkTriggers($user2);

    expect($result1['completed'])->toHaveCount(1);
    expect($result2['completed'])->toBeEmpty();
});

it('skips quests without trigger_class', function () {
    $user = User::factory()->create();
    GameState::factory()->create(['user_id' => $user->id, 'cash' => 1000000, 'xp' => 0, 'day' => 1]);

    Quest::factory()->create([
        'type' => 'inventory',
        'target_value' => 1,
        'trigger_class' => null,
    ]);

    $service = app(QuestService::class);
    $result = $service->checkTriggers($user);

    expect($result['completed'])->toBeEmpty();
});
