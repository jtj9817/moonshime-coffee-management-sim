# Guaranteed Spike Generation Plan

**Created**: 2026-01-19
**Status**: Draft
**Purpose**: Ensure at least one spike event triggers per day and pre-seed initial spikes for new games

---

## Problem Statement

The current spike generation system has two issues:

1. **No initial spikes**: New games start with zero seeded spikes, meaning Day 1 → Day 2 transition likely has no active events
2. **Future scheduling only**: `SpikeEventFactory::generate()` schedules spikes for `currentDay + 1`, creating a lag where early game days feel uneventful
3. **No generation guarantee**: Spike generation can silently fail if required resources (routes, locations) don't exist

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

### Phase 1: Database Schema Updates

#### Task 1.1: Add spike tracking columns to `spike_events` table

**File**: `database/migrations/YYYY_MM_DD_add_spike_generation_tracking.php`

```php
Schema::table('spike_events', function (Blueprint $table) {
    // Track if spike was system-generated (guaranteed) vs random
    $table->boolean('is_guaranteed')->default(false);
});
```

#### Task 1.2: Add spike configuration to `game_states` table

**File**: `database/migrations/YYYY_MM_DD_add_spike_config_to_game_states.php`

```php
Schema::table('game_states', function (Blueprint $table) {
    // Track last generated spike type for cooldown logic
    $table->json('spike_cooldowns')->nullable(); // {"demand": 5, "blizzard": 3} = day last used
});
```

---

### Phase 2: Core Services

#### Task 2.1: Create `SpikeConstraintChecker` service

**File**: `app/Services/SpikeConstraintChecker.php`

**Responsibilities**:
- Check if active spike count is below cap (2)
- Check if spike type is on cooldown
- Return list of allowed spike types for current day

```php
class SpikeConstraintChecker
{
    const MAX_ACTIVE_SPIKES = 2;
    const TYPE_COOLDOWN_DAYS = 2;

    public function canGenerateSpike(int $userId): bool;
    public function getActiveSpikesCount(int $userId): int;
    public function getAllowedTypes(GameState $gameState): array;
    public function recordSpikeGeneration(GameState $gameState, string $type): void;
}
```

#### Task 2.2: Create `GuaranteedSpikeGenerator` service

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
    // 1. Check if we can generate (under cap)
    if (!$this->constraintChecker->canGenerateSpike($gameState->user_id)) {
        return null; // At capacity
    }

    // 2. Get allowed types (respecting cooldown)
    $allowedTypes = $this->constraintChecker->getAllowedTypes($gameState);

    if (empty($allowedTypes)) {
        return null; // All types on cooldown (rare edge case)
    }

    // 3. Generate spike with allowed type, starting TODAY
    $spike = $this->factory->generateWithConstraints(
        currentDay: $currentDay,
        allowedTypes: $allowedTypes,
        startDay: $currentDay // Key difference: starts today, not tomorrow
    );

    // 4. Record for cooldown tracking
    if ($spike) {
        $this->constraintChecker->recordSpikeGeneration($gameState, $spike->type);
    }

    return $spike;
}
```

#### Task 2.3: Update `SpikeEventFactory`

**File**: `app/Services/SpikeEventFactory.php`

**Changes**:
- Add new method `generateWithConstraints()` that accepts allowed types and start day
- Refactor existing `generate()` to use new method internally

```php
/**
 * Generate spike with specific constraints.
 */
public function generateWithConstraints(
    int $currentDay,
    array $allowedTypes,
    int $startDay
): ?SpikeEvent;
```

---

### Phase 3: Initial Spike Seeding

#### Task 3.1: Create `SpikeSeeder` seeder

**File**: `database/seeders/SpikeSeeder.php`

**Responsibilities**:
- Called when new game starts (after user creation)
- Generate 3-5 spikes distributed across Days 2-7
- Ensure variety (no consecutive same-type spikes)

```php
class SpikeSeeder extends Seeder
{
    public function run(): void
    {
        // Get the test user's game state
        $gameState = GameState::first();

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

        $lastType = null;
        $factory = app(SpikeEventFactory::class);

        foreach ($selectedDays as $day) {
            // Exclude last type to ensure variety
            $excludeTypes = $lastType ? [$lastType] : [];

            $spike = $factory->generateForDay(
                userId: $gameState->user_id,
                targetDay: $day,
                excludeTypes: $excludeTypes
            );

            if ($spike) {
                $spike->update(['is_guaranteed' => true]);
                $lastType = $spike->type;
            }
        }
    }
}
```

#### Task 3.2: Update `DatabaseSeeder`

**File**: `database/seeders/DatabaseSeeder.php`

```php
public function run(): void
{
    // ... existing seeders ...

    $this->call(SpikeSeeder::class); // Add after CoreGameStateSeeder
}
```

#### Task 3.3: Create action for new user initialization

**File**: `app/Actions/InitializeNewGame.php`

**Purpose**: Reusable action for initializing spikes when a real user starts a new game (not just seeder)

```php
class InitializeNewGame
{
    public function handle(User $user): void
    {
        $gameState = $user->gameState;

        app(SpikeSeeder::class)->seedInitialSpikes($gameState);
    }
}
```

---

### Phase 4: Integration with Simulation Loop

#### Task 4.1: Update `SimulationService::processEventTick()`

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

    // 2. Start spikes that reach their starts_at_day
    // ... existing code ...

    // 3. GUARANTEED: Ensure at least one spike exists for today
    $this->ensureGuaranteedSpike($day);

    // 4. OPTIONAL: Generate a future spike (existing behavior)
    app(\App\Services\SpikeEventFactory::class)->generate($day);
}

protected function ensureGuaranteedSpike(int $day): void
{
    $userId = $this->gameState->user_id;

    // Check if any spike is already active or starting today
    $hasActiveOrStarting = SpikeEvent::where('user_id', $userId)
        ->where(function ($q) use ($day) {
            $q->where('is_active', true)
              ->orWhere('starts_at_day', $day);
        })
        ->exists();

    if (!$hasActiveOrStarting) {
        // Generate a guaranteed spike for today
        app(GuaranteedSpikeGenerator::class)->generate($this->gameState, $day);
    }
}
```

---

### Phase 5: Testing

#### Task 5.1: Unit tests for `SpikeConstraintChecker`

**File**: `tests/Unit/Services/SpikeConstraintCheckerTest.php`

```php
test('canGenerateSpike returns false when at cap', function () { ... });
test('canGenerateSpike returns true when under cap', function () { ... });
test('getAllowedTypes excludes types on cooldown', function () { ... });
test('getAllowedTypes returns all types when no cooldown', function () { ... });
test('recordSpikeGeneration updates cooldown tracking', function () { ... });
```

#### Task 5.2: Unit tests for `GuaranteedSpikeGenerator`

**File**: `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php`

```php
test('generates spike starting on current day', function () { ... });
test('returns null when at active spike cap', function () { ... });
test('respects type cooldown constraints', function () { ... });
test('falls back to available type when preferred unavailable', function () { ... });
```

#### Task 5.3: Feature test for initial seeding

**File**: `tests/Feature/InitialSpikeSeederTest.php`

```php
test('new game has 3-5 spikes seeded for days 2-7', function () { ... });
test('seeded spikes have variety in types', function () { ... });
test('seeded spikes are marked as guaranteed', function () { ... });
```

#### Task 5.4: Feature test for guaranteed generation

**File**: `tests/Feature/GuaranteedSpikeGenerationTest.php`

```php
test('simulation loop generates spike if none active', function () { ... });
test('simulation loop skips generation if spike already active', function () { ... });
test('respects max 2 concurrent spike cap', function () { ... });
test('respects 2-day type cooldown', function () { ... });
```

---

## File Summary

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_spike_generation_tracking.php` | Create | Add `is_guaranteed` column |
| `database/migrations/*_add_spike_config_to_game_states.php` | Create | Add `spike_cooldowns` JSON column |
| `app/Services/SpikeConstraintChecker.php` | Create | Constraint validation service |
| `app/Services/GuaranteedSpikeGenerator.php` | Create | Guaranteed spike generation logic |
| `app/Services/SpikeEventFactory.php` | Modify | Add `generateWithConstraints()` method |
| `app/Services/SimulationService.php` | Modify | Add `ensureGuaranteedSpike()` call |
| `database/seeders/SpikeSeeder.php` | Create | Initial spike seeding |
| `database/seeders/DatabaseSeeder.php` | Modify | Call SpikeSeeder |
| `app/Actions/InitializeNewGame.php` | Create | Reusable game initialization |
| `tests/Unit/Services/SpikeConstraintCheckerTest.php` | Create | Unit tests |
| `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php` | Create | Unit tests |
| `tests/Feature/InitialSpikeSeederTest.php` | Create | Feature tests |
| `tests/Feature/GuaranteedSpikeGenerationTest.php` | Create | Feature tests |

---

## Execution Order

1. **Migrations** - Run schema updates first
2. **SpikeConstraintChecker** - Core constraint logic (no dependencies)
3. **SpikeEventFactory update** - Add new method
4. **GuaranteedSpikeGenerator** - Depends on above two
5. **SpikeSeeder** - Depends on factory
6. **SimulationService update** - Integration point
7. **Tests** - Verify all components
8. **DatabaseSeeder update** - Final integration

---

## Edge Cases to Handle

1. **All types on cooldown**: If all 5 spike types are on 2-day cooldown (unlikely but possible), skip guaranteed generation for that day
2. **No valid resources**: If blizzard is only allowed type but no vulnerable routes exist, fall back to other types
3. **Game day 1**: Don't generate guaranteed spikes on Day 1 (allow tutorial grace period)
4. **Mid-game start**: If player somehow starts at Day > 1, seed appropriate spikes

---

## Rollback Plan

If issues arise:
1. Migrations can be rolled back (`php artisan migrate:rollback`)
2. `is_guaranteed` flag allows easy identification of system-generated spikes
3. `ensureGuaranteedSpike()` can be disabled by commenting out single line in SimulationService

---

## Success Criteria

- [ ] New games have 3-5 spikes scheduled for Days 2-7
- [ ] Every day progression has at least one active spike (after Day 1)
- [ ] Never more than 2 spikes active simultaneously
- [ ] Same spike type doesn't repeat within 2 days
- [ ] All existing tests continue to pass
- [ ] New tests cover constraint logic
