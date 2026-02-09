<?php

/**
 * Manual Verification Script: Phase 2 - Core Engagement & Progression
 *
 * Validates:
 * 1. Quest System - trigger architecture, progress tracking, reward granting
 * 2. Spike Resolution - audit trail creation, mitigation effects
 *
 * Usage: ./vendor/bin/sail php tests/manual/verify_phase2_engagement_progression.php
 */

require_once __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\Quest;
use App\Models\SpikeEvent;
use App\Models\SpikeResolution;
use App\Models\User;
use App\Models\UserQuest;
use App\QuestTriggers\DaysPlayedTrigger;
use App\QuestTriggers\OrdersPlacedTrigger;
use App\Services\QuestService;
use App\Services\SpikeResolutionService;

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

echo "=== Phase 2: Core Engagement & Progression - Verification ===\n\n";

// ---- SETUP ----
echo "--- Setup ---\n";

$testEmail = 'verify-phase2-'.uniqid().'@test.com';
$user = User::create([
    'name' => 'Phase 2 Verifier',
    'email' => $testEmail,
    'password' => bcrypt('password'),
]);

$gameState = GameState::create([
    'user_id' => $user->id,
    'cash' => 1000000,
    'xp' => 0,
    'day' => 10,
]);

echo "Created test user: {$user->email}\n";
echo "Created game state: cash={$gameState->cash}, day={$gameState->day}\n\n";

// ---- QUEST SYSTEM VERIFICATION ----
echo "--- Quest System ---\n";

// Create test quests
$orderQuest = Quest::create([
    'type' => 'orders_placed',
    'title' => 'Test: Place 2 Orders',
    'description' => 'Place 2 orders to verify quest trigger.',
    'target_value' => 2,
    'reward_cash_cents' => 50000,
    'reward_xp' => 100,
    'is_active' => true,
    'sort_order' => 99,
    'trigger_class' => OrdersPlacedTrigger::class,
]);

$dayQuest = Quest::create([
    'type' => 'days_played',
    'title' => 'Test: Survive 5 Days',
    'description' => 'Reach day 5.',
    'target_value' => 5,
    'reward_cash_cents' => 25000,
    'reward_xp' => 50,
    'is_active' => true,
    'sort_order' => 100,
    'trigger_class' => DaysPlayedTrigger::class,
]);

echo "Created test quests\n";

$service = app(QuestService::class);

// Record starting cash/xp
$startCash = $gameState->cash;
$startXp = $gameState->xp;

// First trigger check: days quest should complete, order quest should not
$result = $service->checkTriggers($user);

// Find which of our test quests completed
$dayCompleted = collect($result['completed'])->contains('quest_id', $dayQuest->id);
$orderCompleted = collect($result['completed'])->contains('quest_id', $orderQuest->id);

check('Days played quest completed (day 10 > target 5)', $dayCompleted);
check('Order quest NOT completed with 0 orders', ! $orderCompleted);

// Verify rewards: at minimum the days quest reward was granted
$gameState->refresh();
check('Cash increased after days quest reward', $gameState->cash > $startCash);
check('XP increased after days quest reward', $gameState->xp > $startXp);

$cashAfterDays = $gameState->cash;
$xpAfterDays = $gameState->xp;

// Create orders and re-check
Order::factory()->count(2)->create(['user_id' => $user->id]);

$result2 = $service->checkTriggers($user);
$orderCompleted2 = collect($result2['completed'])->contains('quest_id', $orderQuest->id);
check('Order quest completed after 2 orders', $orderCompleted2);

$gameState->refresh();
// Cash/XP should have increased by at least the order quest reward (other quests may also complete)
check('Cash increased after order quest reward', $gameState->cash >= $cashAfterDays + $orderQuest->reward_cash_cents);
check('XP increased after order quest reward', $gameState->xp >= $xpAfterDays + $orderQuest->reward_xp);

$finalCash = $gameState->cash;

// Verify idempotency
$result3 = $service->checkTriggers($user);
$anyTestQuestCompleted = collect($result3['completed'])->whereIn('quest_id', [$dayQuest->id, $orderQuest->id])->isNotEmpty();
check('Re-check does not re-grant test quest rewards', ! $anyTestQuestCompleted);
$gameState->refresh();
check('Cash unchanged after re-check for test quests', $gameState->cash === $finalCash);

echo "\n--- Spike Resolution Audit Trail ---\n";

// Create a test spike
$testLocation = Location::factory()->create(['type' => 'warehouse']);
$spike = SpikeEvent::factory()->create([
    'user_id' => $user->id,
    'type' => 'breakdown',
    'magnitude' => 0.5,
    'location_id' => $testLocation->id,
    'is_active' => true,
    'starts_at_day' => 8,
    'ends_at_day' => 15,
]);

$resService = app(SpikeResolutionService::class);

// Test mitigation creates audit record
$resService->mitigate($spike, 'emergency_repair');
$mitigateRes = SpikeResolution::where('spike_event_id', $spike->id)
    ->where('action_type', 'mitigate')
    ->first();
check('Mitigation creates SpikeResolution record', $mitigateRes !== null);
check('Mitigation record has correct action_detail', $mitigateRes?->action_detail === 'emergency_repair');

// Test resolve creates audit record
$spike->refresh();
$spike->update(['is_active' => true]); // Re-activate for resolve test
$resService->resolveEarly($spike);
$resolveRes = SpikeResolution::where('spike_event_id', $spike->id)
    ->where('action_type', 'resolve_early')
    ->first();
check('Early resolve creates SpikeResolution record', $resolveRes !== null);
check('Resolve record has cost > 0', $resolveRes?->cost_cents > 0);
check('Resolve record has game_day = 10', $resolveRes?->game_day === 10);

// ---- TEARDOWN ----
echo "\n--- Teardown ---\n";

SpikeResolution::where('user_id', $user->id)->delete();
SpikeEvent::where('user_id', $user->id)->delete();
UserQuest::where('user_id', $user->id)->delete();
Order::where('user_id', $user->id)->delete();
$orderQuest->delete();
$dayQuest->delete();
GameState::where('user_id', $user->id)->delete();
$testLocation->delete();
$user->delete();

echo "Cleanup complete — test data removed.\n\n";

echo "=== Results: {$passed} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
