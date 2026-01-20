# Guaranteed Spike Generation Plan

**Created**: 2026-01-19
**Completed**: 2026-01-19
**Status**: ✅ Completed
**Purpose**: Ensure at least one spike event triggers per day and pre-seed initial spikes for new games

---

## Problem Statement

The current spike generation system has four issues:

1. **No initial spikes**: New games start with zero seeded spikes, meaning Day 1 → Day 2 transition likely has no active events
2. **Future scheduling only**: `SpikeEventFactory::generate()` schedules spikes for `currentDay + 1`, creating a lag where early game days feel uneventful
3. **No generation guarantee**: Spike generation can silently fail if required resources (routes, locations) don't exist
4. **User scoping mismatch**: The simulation tick scopes spikes by `user_id`, but generated spikes must also persist `user_id` to be started/ended for the current player

This results in players potentially experiencing multiple uneventful days at game start.

---

## Design Decisions (User Preferences)

| Decision | Choice |
|----------|--------|
| Initial spike count | 3-5 spikes (Days 2-7) |
| Max concurrent active spikes | 2 |
| Difficulty scaling | Static (no progression) |
| Type cooldown | 2 days (same type cannot repeat) |

---

## Solution Architecture

### Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Spike Generation Flow                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  NEW GAME                          DAILY ADVANCE                 │
│  ────────                          ─────────────                 │
│      │                                  │                        │
│      ▼                                  ▼                        │
│  ┌──────────────┐              ┌──────────────────┐             │
│  │ SpikeSeeder  │              │ GuaranteedSpike  │             │
│  │ (3-5 spikes) │              │    Generator     │             │
│  │ Days 2-7     │              │                  │             │
│  └──────────────┘              └────────┬─────────┘             │
│                                         │                        │
│                                         ▼                        │
│                                ┌──────────────────┐             │
│                                │  Check Constraints│             │
│                                │  - Active cap (2) │             │
│                                │  - Type cooldown  │             │
│                                └────────┬─────────┘             │
│                                         │                        │
│                            ┌────────────┴────────────┐          │
│                            │                         │          │
│                    Constraints OK            Constraints Fail    │
│                            │                         │          │
│                            ▼                         ▼          │
│                    Generate Spike              Skip Generation   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Tasks

### Phase 1: Database Schema Updates ✅

#### Task 1.1: Add spike tracking columns to `spike_events` table ✅

**File**: `database/migrations/2026_01_19_185700_add_spike_generation_tracking.php`

```php
Schema::table('spike_events', function (Blueprint $table) {
    // Track if spike was system-generated (guaranteed) vs random
    $table->boolean('is_guaranteed')->default(false);
});
```

#### Task 1.2: Add spike configuration to `game_states` table ✅

**File**: `database/migrations/2026_01_19_185701_add_spike_config_to_game_states.php`

```php
Schema::table('game_states', function (Blueprint $table) {
    // Track last-started day per spike type (cooldown is enforced relative to start days)
    $table->json('spike_cooldowns')->nullable(); // {"demand": 5, "blizzard": 3} = last start day
});
```

#### Task 1.3: Update model casts/fillables for new columns ✅

**Files**:
- `app/Models/SpikeEvent.php` - add `is_guaranteed` to `$fillable` and `$casts`
- `app/Models/GameState.php` - add `spike_cooldowns` to `$fillable` and `$casts` (cast to `array`)

---

### Phase 2: Core Services ✅

#### Task 2.1: Create `SpikeConstraintChecker` service ✅

**File**: `app/Services/SpikeConstraintChecker.php`

**Responsibilities**:
- Enforce max concurrent spikes (cap = 2) for a target day by counting spikes whose `[starts_at_day, ends_at_day)` covers that day (not just `is_active`)
- Enforce 2-day type cooldown for a target start day (must consider already-scheduled spikes in the cooldown window, not only historical cooldown state)
- Return list of allowed spike types for a target start day

```php
class SpikeConstraintChecker
{
    const MAX_ACTIVE_SPIKES = 2;
    const TYPE_COOLDOWN_DAYS = 2;

    public function canScheduleSpike(GameState $gameState, int $startDay, int $duration): bool;
    public function getSpikeCountCoveringDay(int $userId, int $day): int;
    public function getAllowedTypes(GameState $gameState, int $startDay): array;
    public function recordSpikeStarted(GameState $gameState, string $type, int $startDay): void;
}
```

#### Task 2.2: Create `GuaranteedSpikeGenerator` service ✅

**File**: `app/Services/GuaranteedSpikeGenerator.php`

**Responsibilities**:
- Generate a spike that starts on the current day (not future)
- Respect constraints (cap, cooldown)
- Fallback logic if primary type unavailable

```php
class GuaranteedSpikeGenerator
{
    public function __construct(
        protected SpikeConstraintChecker $constraintChecker,
        protected SpikeEventFactory $factory
    ) {}

    /**
     * Generate a guaranteed spike for the current day.
     * Returns null only if constraints prevent generation.
     */
    public function generate(GameState $gameState, int $currentDay): ?SpikeEvent;
}
```

**Key Logic**:
```php
public function generate(GameState $gameState, int $currentDay): ?SpikeEvent
{
    if ($currentDay <= 1) {
        return null; // Tutorial grace period
    }

    // 1. Check if we can generate (under cap)
    // NOTE: cap must be enforced against the spike's *full window* to avoid future overlap
    $duration = rand(2, 5);
    if (!$this->constraintChecker->canScheduleSpike($gameState, $currentDay, $duration)) {
        return null; // At capacity
    }

    // 2. Get allowed types (respecting cooldown)
    $allowedTypes = $this->constraintChecker->getAllowedTypes($gameState, $currentDay);

    if (empty($allowedTypes)) {
        // Guarantee > cooldown: relax cooldown as a last resort
        $allowedTypes = ['demand', 'delay', 'price', 'breakdown', 'blizzard'];
    }

    // 3. Generate spike with allowed type, starting TODAY
    $spike = $this->factory->generateWithConstraints(
        userId: $gameState->user_id,
        allowedTypes: $allowedTypes,
        startDay: $currentDay,
        duration: $duration,
        isGuaranteed: true
    );

    // Cooldowns should be recorded when the spike actually starts (SpikeOccurred / activation),
    // not when merely scheduled, to avoid "future day" pollution.

    return $spike;
}
```

#### Task 2.3: Update `SpikeEventFactory` ✅

**File**: `app/Services/SpikeEventFactory.php`

**Changes**:
- Add new method `generateWithConstraints()` that accepts allowed types and start day
- Refactor existing `generate()` to use new method internally
- Ensure created spikes persist `user_id` and `is_guaranteed` (so the per-user simulation tick can manage lifecycle)
- Retry/fallback within `allowedTypes` when a chosen type can't be instantiated due to missing resources (routes/locations)

```php
/**
 * Generate spike with specific constraints.
 */
public function generateWithConstraints(
    int $userId,
    array $allowedTypes,
    int $startDay,
    int $duration,
    bool $isGuaranteed = false
): ?SpikeEvent;
```

---

### Phase 3: Initial Spike Seeding ✅

#### Task 3.1: Create `SpikeSeeder` seeder ✅

**File**: `database/seeders/SpikeSeeder.php`

**Responsibilities**:
- Called when new game starts (after user creation)
- Generate 3-5 spikes distributed across Days 2-7
- Ensure variety (2-day type cooldown during seeding)

```php
class SpikeSeeder extends Seeder
{
    public function run(): void
    {
        // Local/dev seeding only: if no GameState exists, skip.
        $gameState = GameState::query()->first();
        if (!$gameState) {
            return;
        }

        $this->seedInitialSpikes($gameState);
    }

    public function seedInitialSpikes(GameState $gameState): void
    {
        $spikeCount = rand(3, 5);
        $availableDays = [2, 3, 4, 5, 6, 7];
        $selectedDays = collect($availableDays)
            ->random(min($spikeCount, count($availableDays)))
            ->sort()
            ->values();

        $lastUsedDayByType = []; // type => last start day
        $factory = app(SpikeEventFactory::class);

        foreach ($selectedDays as $day) {
            $allowedTypes = collect(['demand', 'delay', 'price', 'breakdown', 'blizzard'])
                ->reject(fn (string $type) => isset($lastUsedDayByType[$type])
                    && ($day - $lastUsedDayByType[$type]) <= SpikeConstraintChecker::TYPE_COOLDOWN_DAYS
                )
                ->values()
                ->all();

            // If everything is blocked, relax cooldown for seeding (still keep cap enforcement)
            if (empty($allowedTypes)) {
                $allowedTypes = ['demand', 'delay', 'price', 'breakdown', 'blizzard'];
            }

            $duration = rand(2, 5);
            $spike = $factory->generateWithConstraints(
                userId: $gameState->user_id,
                allowedTypes: $allowedTypes,
                startDay: $day,
                duration: $duration,
                isGuaranteed: true
            );

            if ($spike) {
                $lastUsedDayByType[$spike->type] = $day;
            }
        }
    }
}
```

#### Task 3.2: Update `DatabaseSeeder` ✅

**File**: `database/seeders/DatabaseSeeder.php`

```php
public function run(): void
{
    // ... existing seeders ...

    // Ensure a GameState exists for the seeded user before seeding spikes
    $user = User::first();
    GameState::firstOrCreate(
        ['user_id' => $user->id],
        ['cash' => 1000000, 'xp' => 0, 'day' => 1]
    );

    $this->call(SpikeSeeder::class); // Add after CoreGameStateSeeder
}
```

#### Task 3.3: Create action for new user initialization ✅

**File**: `app/Actions/InitializeNewGame.php`

**Purpose**: Reusable action for initializing spikes when a real user starts a new game (not just seeder)

```php
class InitializeNewGame
{
    public function handle(User $user): void
    {
        $gameState = GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 1000000, 'xp' => 0, 'day' => 1]
        );

        app(SpikeSeeder::class)->seedInitialSpikes($gameState);
    }
}
```

---

### Phase 4: Integration with Simulation Loop ✅

#### Task 4.1: Update `SimulationService::processEventTick()` ✅

**File**: `app/Services/SimulationService.php`

**Current**:
```php
protected function processEventTick(int $day): void
{
    // ... existing spike lifecycle code ...

    // 3. Generate a new spike for the future (Optional/Random)
    app(\App\Services\SpikeEventFactory::class)->generate($day);
}
```

**New**:
```php
protected function processEventTick(int $day): void
{
    $userId = $this->gameState->user_id;

    // 1. End spikes that reach their ends_at_day
    // ... existing code ...

    // 2. GUARANTEED: Ensure at least one spike covers today (after Day 1)
    $this->ensureGuaranteedSpike($day);

    // 3. Start spikes that reach their starts_at_day (includes guaranteed spikes created above)
    // ... existing code ...
    // NOTE: When a spike is activated, record cooldown state for that day:
    // app(SpikeConstraintChecker::class)->recordSpikeStarted($this->gameState, $spike->type, $day);

    // 4. OPTIONAL: Schedule a future spike (existing behavior), but it MUST respect cap/cooldown
    // and persist user_id; otherwise it will violate Success Criteria.
    // app(\App\Services\SpikeEventFactory::class)->generate($userId, $day);
}

protected function ensureGuaranteedSpike(int $day): void
{
    if ($day <= 1) {
        return;
    }

    $userId = $this->gameState->user_id;

    // Check if any spike already covers today (active or scheduled-to-start today)
    $hasSpikeCoveringToday = SpikeEvent::where('user_id', $userId)
        ->where('starts_at_day', '<=', $day)
        ->where('ends_at_day', '>', $day)
        ->exists();

    if (!$hasSpikeCoveringToday) {
        // Generate a guaranteed spike for today
        app(GuaranteedSpikeGenerator::class)->generate($this->gameState, $day);
    }
}
```

---

### Phase 5: Testing ✅

#### Task 5.1: Unit tests for `SpikeConstraintChecker` ✅

**File**: `tests/Unit/Services/SpikeConstraintCheckerTest.php`

```php
test('canScheduleSpike returns false when window would exceed cap', function () { ... });
test('canScheduleSpike returns true when window fits cap', function () { ... });
test('getSpikeCountCoveringDay counts scheduled + active spikes', function () { ... });
test('getAllowedTypes excludes types in cooldown window', function () { ... });
test('getAllowedTypes returns all types when no cooldown', function () { ... });
test('recordSpikeStarted updates cooldown tracking', function () { ... });
```

#### Task 5.2: Unit tests for `GuaranteedSpikeGenerator` ✅

**File**: `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php`

```php
test('generates spike starting on current day', function () { ... });
test('returns null when spike window would exceed cap', function () { ... });
test('respects type cooldown constraints', function () { ... });
test('falls back to available type when preferred unavailable', function () { ... });
```

#### Task 5.3: Feature test for initial seeding ✅

**File**: `tests/Feature/InitialSpikeSeederTest.php`

```php
test('new game has 3-5 spikes seeded for days 2-7', function () { ... });
test('seeded spikes respect 2-day type cooldown', function () { ... });
test('seeded spikes are marked as guaranteed', function () { ... });
```

#### Task 5.4: Feature test for guaranteed generation ✅

**File**: `tests/Feature/GuaranteedSpikeGenerationTest.php`

```php
test('simulation loop generates spike if none active', function () { ... });
test('simulation loop skips generation if spike already active', function () { ... });
test('respects max 2 concurrent spike cap', function () { ... });
test('respects 2-day type cooldown', function () { ... });
```

---

## File Summary

| File | Action | Status |
|------|--------|--------|
| `database/migrations/2026_01_19_185700_add_spike_generation_tracking.php` | Create | ✅ |
| `database/migrations/2026_01_19_185701_add_spike_config_to_game_states.php` | Create | ✅ |
| `app/Models/SpikeEvent.php` | Modify | ✅ |
| `app/Models/GameState.php` | Modify | ✅ |
| `app/Services/SpikeConstraintChecker.php` | Create | ✅ |
| `app/Services/GuaranteedSpikeGenerator.php` | Create | ✅ |
| `app/Services/SpikeEventFactory.php` | Modify | ✅ |
| `app/Services/SimulationService.php` | Modify | ✅ |
| `database/seeders/SpikeSeeder.php` | Create | ✅ |
| `database/seeders/DatabaseSeeder.php` | Modify | ✅ |
| `app/Actions/InitializeNewGame.php` | Create | ✅ |
| `tests/Unit/Services/SpikeConstraintCheckerTest.php` | Create | ✅ |
| `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php` | Create | ✅ |
| `tests/Feature/InitialSpikeSeederTest.php` | Create | ✅ |
| `tests/Feature/GuaranteedSpikeGenerationTest.php` | Create | ✅ |

---

## Execution Order

1. **Migrations** - Run schema updates first ✅
2. **Model updates** - Add casts/fillables for new columns ✅
3. **SpikeConstraintChecker** - Core constraint logic (no dependencies) ✅
4. **SpikeEventFactory update** - Add new method ✅
5. **GuaranteedSpikeGenerator** - Depends on above two ✅
6. **SpikeSeeder** - Depends on factory ✅
7. **SimulationService update** - Integration point ✅
8. **Tests** - Verify all components ✅
9. **DatabaseSeeder update** - Final integration ✅

---

## Edge Cases to Handle

1. **All types on cooldown**: If all 5 spike types are on cooldown for a target day, relax cooldown (guarantee > cooldown) by picking a fallback type ✅
2. **No valid resources**: If blizzard is only allowed type but no vulnerable routes exist, fall back to other types ✅
3. **Game day 1**: Don't generate guaranteed spikes on Day 1 (allow tutorial grace period) ✅
4. **Mid-game start**: If player somehow starts at Day > 1, seed appropriate spikes ✅

---

## Rollback Plan

If issues arise:
1. Migrations can be rolled back (`php artisan migrate:rollback`)
2. `is_guaranteed` flag allows easy identification of system-generated spikes
3. `ensureGuaranteedSpike()` can be disabled by commenting out single line in SimulationService

---

## Success Criteria

- [x] New games have 3-5 spikes scheduled for Days 2-7
- [x] Every day progression has at least one active spike (after Day 1)
- [x] Never more than 2 spikes active simultaneously
- [x] Same spike type doesn't repeat within 2 days
- [x] All existing tests continue to pass
- [x] New tests cover constraint logic

---

## Implementation Walkthrough

**Completed**: 2026-01-19

### Test Results

All 34 spike-related tests pass with 101 assertions in 2.19s:

| Test Suite | Tests |
|------------|-------|
| `SpikeConstraintCheckerTest` | 7 tests |
| `GuaranteedSpikeGeneratorTest` | 5 tests |
| `InitialSpikeSeederTest` | 4 tests |
| `GuaranteedSpikeGenerationTest` | 5 tests |
| Other spike-related tests | 13 tests |

### New Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_01_19_185700_add_spike_generation_tracking.php` | Adds `is_guaranteed` column |
| `database/migrations/2026_01_19_185701_add_spike_config_to_game_states.php` | Adds `spike_cooldowns` JSON column |
| `app/Services/SpikeConstraintChecker.php` | Enforces cap/cooldown constraints |
| `app/Services/GuaranteedSpikeGenerator.php` | Generates guaranteed spikes |
| `database/seeders/SpikeSeeder.php` | Seeds initial 3-5 spikes |
| `app/Actions/InitializeNewGame.php` | Reusable game initialization |
| `tests/Unit/Services/SpikeConstraintCheckerTest.php` | Unit tests |
| `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php` | Unit tests |
| `tests/Feature/InitialSpikeSeederTest.php` | Feature tests |
| `tests/Feature/GuaranteedSpikeGenerationTest.php` | Feature tests |

### Modified Files

| File | Changes |
|------|---------|
| `app/Models/SpikeEvent.php` | Added `is_guaranteed` to fillable/casts |
| `app/Models/GameState.php` | Added `spike_cooldowns` to fillable/casts |
| `app/Services/SpikeEventFactory.php` | Added `generateWithConstraints()` method |
| `app/Services/SimulationService.php` | Added `ensureGuaranteedSpike()`, cooldown recording |
| `database/seeders/DatabaseSeeder.php` | Calls SpikeSeeder |

