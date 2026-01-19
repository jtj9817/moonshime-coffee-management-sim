# Gameplay Loop Mechanics Analysis

**Date:** 2026-01-19
**Version:** 1.0
**Status:** Analysis Complete

## Executive Summary

This document provides a comprehensive analysis of the gameplay loop mechanics in Moonshine Coffee Management Sim. Key findings:

- **Initial State:** Players start at Day 1 with $10,000 cash, 0 XP, baseline inventory, and seeded pipeline activity
- **Loop Structure:** Player-initiated turn-based system; day increments first, then event/physics/analysis ticks
- **End Condition:** No hard day limit - designed for indefinite sandbox play
- **Decision Tracking:** All player actions persisted via PostgreSQL with `user_id` foreign keys
- **Reset Feasibility:** Low effort (~1-2 hours, 6 files) with no schema blockers

---

## 1. Initial Gameplay State

### 1.1 Game State Initialization

**Location:** `app/Http/Middleware/HandleInertiaRequests.php` (`getGameState`) and `app/Actions/InitializeNewGame.php`

When an authenticated user loads the game, the state is created (if missing) and per-user seeding runs for a fresh game:

```php
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 1000000, 'xp' => 0, 'day' => 1]
);
```

### 1.2 Initial Values

| Field | Value | Notes |
|-------|-------|-------|
| **Cash** | 1,000,000 cents | $10,000 starting capital |
| **XP** | 0 | Experience points |
| **Day** | 1 | Starting day counter |
| **Level** | 1 | Computed: `floor(xp / 1000) + 1` |
| **Reputation** | 85 | Computed: 85 baseline - (3 × unread alerts) |
| **Strikes** | 0 | Computed: count of critical alerts |

### 1.3 Pre-Populated World Data

**Seeded via:** `database/seeders/DatabaseSeeder.php`

**Global world data:**
- **Locations:** Vendor/warehouse/store/hub graph (12 total from `GraphSeeder` + `CoreGameStateSeeder`)
- **Products:** 5 core SKUs (beans, milk, cups)
- **Vendors:** 3 suppliers (Bean Baron, Dairy King, General Supplies Co)
- **Routes:** 28 logistics routes connecting the graph

**Per-user game data (fresh game via `InitializeNewGame`):**
- **Inventory:** Baseline stock per location (store + warehouse + secondary stores)
- **Orders:** 1 shipped order seeded to arrive on Day 3
- **Transfers:** 2 in-transit transfers seeded to arrive on Days 2 and 4
- **Spike events:** 3-5 guaranteed spikes scheduled across Days 2-7
- **Alerts:** None by default

### 1.4 Database Schema

**Migration:** `database/migrations/2026_01_16_055234_create_game_states_table.php`

```php
Schema::create('game_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->bigInteger('cash')->default(0);
    $table->integer('xp')->default(0);
    $table->integer('day')->default(1);
    $table->timestamps();
});
```

**Key Constraint:** One `GameState` row per user (unique on `user_id`)

---

## 2. Day Progression Mechanics

### 2.1 Advancement Trigger

**User Action:** Player clicks "Next Day" button
**Component:** `resources/js/components/game/advance-day-button.tsx`
**Route:** `POST /game/advance-day`
**Controller:** `app/Http/Controllers/GameController.php::advanceDay()`
**Service:** `app/Services/SimulationService.php::advanceTime()`

### 2.2 Three-Phase Tick System

The simulation engine increments the day and then runs three sequential phases inside a database transaction:

#### **Phase 1: Event Tick** (lines 45-78)

Handles time-based spike lifecycle and scheduling:

```php
protected function processEventTick(int $day): void
{
    $userId = $this->gameState->user_id;
    $constraintChecker = app(SpikeConstraintChecker::class);

    // End spikes that reach their ends_at_day
    SpikeEvent::where('user_id', $userId)
        ->where('is_active', true)
        ->where('ends_at_day', '<=', $day)
        ->get()
        ->each(function (SpikeEvent $spike) {
            $spike->update(['is_active' => false]);
            event(new SpikeEnded($spike));
        });

    // Ensure at least one spike covers today (after Day 1)
    $this->ensureGuaranteedSpike($day);

    // Start spikes that reach their starts_at_day
    SpikeEvent::where('user_id', $userId)
        ->where('is_active', false)
        ->where('starts_at_day', '<=', $day)
        ->where('ends_at_day', '>', $day)
        ->get()
        ->each(function (SpikeEvent $spike) use ($constraintChecker, $day) {
            $spike->update(['is_active' => true]);
            event(new SpikeOccurred($spike));
            $constraintChecker->recordSpikeStarted($this->gameState, $spike->type, $day);
        });

    // Optionally schedule a future spike when constraints allow
    $this->scheduleOptionalSpike($day, $constraintChecker);
}
```

**Actions:**
- ✅ Deactivate expired demand spikes (`ends_at_day <= current_day`)
- ✅ Ensure a guaranteed spike covers today (after Day 1)
- ✅ Activate scheduled spikes (`starts_at_day <= current_day`)
- ✅ Schedule optional future spikes when constraints allow

#### **Phase 2: Physics Tick** (lines 84-104)

Resolves logistics and deliveries via state transitions:

```php
protected function processPhysicsTick(int $day): void
{
    $userId = $this->gameState->user_id;

    Transfer::where('user_id', $userId)
        ->whereState('status', InTransit::class)
        ->where('delivery_day', '<=', $day)
        ->get()
        ->each(fn ($transfer) => $transfer->status->transitionTo(Completed::class));

    Order::where('user_id', $userId)
        ->whereState('status', Shipped::class)
        ->where('delivery_day', '<=', $day)
        ->get()
        ->each(fn ($order) => $order->status->transitionTo(Delivered::class));
}
```

**Actions:**
- 🚚 Complete transfers where `delivery_day <= current_day`
- 📦 Deliver orders where `delivery_day <= current_day`
- 📊 Inventory updates and alerts occur via event listeners on delivery transitions
- 💰 Apply storage costs and 🗑️ decay perishables via `TimeAdvanced` listeners

#### **Phase 3: Analysis Tick** (lines 106-112)

Runs isolation analysis and alert generation:

```php
protected function processAnalysisTick(int $day): void
{
    app(GenerateIsolationAlerts::class)->handle($this->gameState->user_id);
}
```

**Actions:**
- 🔍 Detect isolated locations
- ⚠️ Generate isolation alerts
- 📈 Recalculate KPIs (cash flow, inventory value)
- 🎯 Generate suggested actions for dashboard

### 2.3 Atomic Day Increment

```php
DB::transaction(function () {
    $this->gameState->increment('day');
    $this->gameState->refresh();
    $day = $this->gameState->day;

    $this->processEventTick($day);
    $this->processPhysicsTick($day);
    $this->processAnalysisTick($day);

    event(new TimeAdvanced($day, $this->gameState));
});
```

**Why Transaction?** Ensures day increment + tick phases are atomic (prevents partial state corruption)

---

## 3. Game Resolution & End Conditions

### 3.1 Current Implementation: **NO END CONDITION**

The game is designed as an **indefinite sandbox simulation** with no hard day limit.

**Evidence:**

1. **No day cap in simulation service:**
   ```php
   // app/Services/SimulationService.php::advanceTime()
   // No check like: if ($this->gameState->day >= 7) { endGame(); }
   ```

2. **No game-over state in database:**
   - No `game_states.is_completed` column
   - No `game_states.end_reason` enum
   - No `completed_at` timestamp

3. **Day counter UI is cosmetic only:**
   ```typescript
   // resources/js/components/game/day-counter.tsx:9
   export function DayCounter({
       day,
       totalDays = 30,  // Display-only prop!
       className = ''
   }: DayCounterProps)
   ```

   The progress bar shows `day / 30` but doesn't block advancement beyond Day 30.

### 3.2 Design Philosophy

The game follows a **continuous simulation model** similar to:
- *SimCity* (no time limit, player-driven challenges)
- *Factorio* (indefinite optimization gameplay)
- *Stardew Valley* (open-ended farm management)

Players can manage their coffee empire as long as they want, with emergent challenges (spikes, vendor delays, logistics failures).

### 3.3 Potential End Conditions (If Implemented)

If you wanted to add a day limit or victory/defeat system, consider:

| Condition Type | Trigger | Implementation |
|----------------|---------|----------------|
| **Day Cap** | `day >= 30` | Block `advanceDay()`, show end screen |
| **Bankruptcy** | `cash < 0` | Auto-end game, show failure screen |
| **Reputation Collapse** | `reputation < 10` | Auto-end game, show failure screen |
| **Strike Limit** | `strikes >= 5` | Three-strikes-you're-out rule |
| **Victory Goal** | `cash >= 1,000,000` | Player wins, optional continue mode |
| **XP Milestone** | `level >= 10` | Story completion, unlock prestige mode |

**Files to modify for end conditions:**
1. `app/Services/SimulationService.php` - Add end condition checks
2. `database/migrations/add_game_over_fields_to_game_states.php` - Add `is_completed`, `end_reason`
3. `app/Http/Controllers/GameController.php` - Add `checkGameOver()` method
4. `resources/js/Pages/game/game-over.tsx` - Create end screen
5. `routes/web.php` - Add `/game/game-over` route

---

## 4. Player Decision Tracking

### 4.1 Architecture Overview

Every player action is persisted with a `user_id` foreign key, enabling per-user game state isolation:

```
PostgreSQL Database
├── game_states (1 row per user)
│   └── [user_id, cash, xp, day]
├── orders (many per user)
│   └── [user_id, vendor_id, items[], status, total_cost]
├── transfers (many per user)
│   └── [user_id, source_id, target_id, product_id, quantity]
├── spike_events (many per user)
│   └── [user_id, starts_at_day, ends_at_day, is_active]
└── alerts (many per user)
    └── [user_id, type, severity, is_read]
```

### 4.2 Purchase Decisions (Orders)

**Model:** `app/Models/Order.php`
**Endpoint:** `POST /game/orders` → `GameController::placeOrder()`
**Validation:** `app/Http/Requests/PlaceOrderRequest.php`

#### Schema
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('vendor_id')->constrained();
    $table->foreignId('location_id')->constrained();
    $table->foreignId('route_id')->nullable()->constrained();
    $table->enum('status', ['Draft', 'Pending', 'Shipped', 'Delivered']);
    $table->bigInteger('total_cost');
    $table->integer('delivery_day');
    $table->timestamps();
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->integer('unit_price');
    $table->timestamps();
});
```

#### Tracked Fields
| Field | Purpose |
|-------|---------|
| `user_id` | WHO made the decision |
| `vendor_id` | WHICH supplier chosen (price/reliability tradeoff) |
| `location_id` | WHERE to deliver |
| `route_id` | WHICH logistics route (speed/cost tradeoff) |
| `items[]` | WHAT to buy (product_id, quantity, unit_price) |
| `status` | State machine tracking |
| `total_cost` | Financial impact on cash |
| `delivery_day` | WHEN goods arrive |

#### State Machine
```
Draft → Pending → Shipped → Delivered
  ↓       ↓         ↓          ↓
(edit) (cancel)  (track)   (complete)
```

### 4.3 Inventory Movements (Transfers)

**Model:** `app/Models/Transfer.php`
**Endpoint:** `POST /game/transfers` → `GameController::createTransfer()`

#### Schema
```php
Schema::create('transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('source_location_id')->constrained('locations');
    $table->foreignId('target_location_id')->constrained('locations');
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->enum('status', ['Draft', 'Pending Approval', 'In Transit', 'Completed']);
    $table->integer('delivery_day');
    $table->timestamps();
});
```

#### Tracked Fields
| Field | Purpose |
|-------|---------|
| `user_id` | WHO initiated transfer |
| `source_location_id` | FROM which location |
| `target_location_id` | TO which location |
| `product_id` | WHAT item to move |
| `quantity` | HOW MUCH to transfer |
| `status` | State machine tracking |
| `delivery_day` | WHEN transfer completes |

### 4.4 Alert Acknowledgements

**Model:** `app/Models/Alert.php`
**Endpoint:** `POST /game/alerts/{id}/read` → `GameController::markAlertRead()`

#### Schema
```php
Schema::create('alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['spike', 'stockout', 'vendor_delay', 'isolation']);
    $table->enum('severity', ['low', 'medium', 'high', 'critical']);
    $table->text('message');
    $table->json('metadata')->nullable();
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

#### Tracked Information
| Field | Purpose |
|-------|---------|
| `type` | Alert category (system-generated events) |
| `severity` | Impact level (affects reputation/strikes) |
| `is_read` | Whether player acknowledged |
| `read_at` | WHEN player saw the alert |
| `metadata` | Event context (spike details, stockout location) |

**Reputation Calculation:**
```php
// app/Http/Middleware/HandleInertiaRequests.php
$reputation = 85;
$alertCount = Alert::where('is_read', false)->count();
$reputation -= min(15, $alertCount * 3);  // -3 per alert, capped at -15
return max(0, min(100, $reputation));
```
Note: current implementation uses global unread alerts (not user-scoped).

### 4.5 Strategy Changes (Future)

**Endpoint:** `PUT /game/policy` → `GameController::updatePolicy()`
**Status:** Stubbed but not fully implemented

Planned tracking:
- Inventory policy: "just_in_time" vs "safety_stock"
- Reorder point strategies
- Risk tolerance levels

### 4.6 Decision History Queries

#### Financial History
```php
Order::where('user_id', auth()->id())
    ->orderBy('created_at')
    ->get(['total_cost', 'delivery_day', 'vendor_id', 'status'])
    ->groupBy('vendor_id')
    ->map(fn($orders) => $orders->sum('total_cost'));
```

#### Logistics Performance
```php
Transfer::where('user_id', auth()->id())
    ->where('status', 'Completed')
    ->avg(DB::raw('delivery_day - created_at_day'));
```

#### Alert Response Time
```php
Alert::where('user_id', auth()->id())
    ->whereNotNull('read_at')
    ->avg(DB::raw('EXTRACT(EPOCH FROM (read_at - created_at)) / 86400'));
```

---

## 5. State Persistence & Sharing

### 5.1 Storage Architecture

**Database:** PostgreSQL
**ORM:** Eloquent (Laravel)
**Sharing Mechanism:** Inertia.js middleware

### 5.2 Middleware Sharing Strategy

**File:** `app/Http/Middleware/HandleInertiaRequests.php` (lines 43-112)

On each request, `game` is lazily resolved for authenticated users:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $request->user(),
        ],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        'game' => fn () => $request->user() ? $this->getGameData($request->user()) : null,
    ];
}

protected function getGameData(User $user): array
{
    return [
        'state' => $this->getGameState($user),
        'locations' => Location::select('id', 'name', 'address', 'max_storage')->get(),
        'products' => Product::with('vendors:id,name')
            ->select('id', 'name', 'category', 'is_perishable', 'storage_cost')
            ->get(),
        'vendors' => Vendor::select('id', 'name', 'reliability_score', 'metrics')->get(),
        'alerts' => Alert::where('is_read', false)->latest()->take(10)->get(),
        'currentSpike' => SpikeEvent::where('is_active', true)->first(),
    ];
}
```

Note: alerts and current spikes are not yet scoped per-user in the middleware.

### 5.3 Frontend Access Pattern

**Context Provider:** `resources/js/contexts/game-context.tsx`

```typescript
import { usePage } from '@inertiajs/react';

export function useGame() {
    const { game } = usePage().props;
    return {
        state: game.state,
        locations: game.locations,
        products: game.products,
        vendors: game.vendors,
        alerts: game.alerts,
        currentSpike: game.currentSpike
    };
}
```

**Usage in Components:**
```typescript
import { useGame } from '@/contexts/game-context';

export function DashboardPage() {
    const { state, alerts, currentSpike } = useGame();

    return (
        <div>
            <DayCounter day={state.day} />
            <CashDisplay cash={state.cash} />
            <AlertList alerts={alerts} />
            {currentSpike && <SpikeWarning spike={currentSpike} />}
        </div>
    );
}
```

### 5.4 Performance Optimization

**Automatic Sharing:** Middleware shares state on every Inertia request (no manual loading needed)

**Partial Reloads:** Components can request specific props only:
```typescript
router.reload({ only: ['alerts'] });  // Only reload alerts, not full game state
```

**Caching Strategy:** (Future improvement)
```php
Cache::remember("game_state_{$userId}", 60, function () use ($userId) {
    return GameState::where('user_id', $userId)->first();
});
```

---

## 6. Reset Feature Implementation

### 6.1 Feasibility Assessment

**Effort:** Low (1-2 hours)
**Files Modified:** 6
**Complexity:** Simple database cleanup and UI wiring (no schema blocker)

### 6.2 Implementation Plan

#### **Step 1: Backend Reset Logic**

**File:** `app/Http/Controllers/GameController.php`

```php
use Illuminate\Support\Facades\DB;

public function resetGame(Request $request)
{
    DB::transaction(function () {
        $userId = auth()->id();

        // Delete all game data (cascades via foreign keys)
        Order::where('user_id', $userId)->delete();        // Also deletes order_items
        Transfer::where('user_id', $userId)->delete();
        Alert::where('user_id', $userId)->delete();
        SpikeEvent::where('user_id', $userId)->delete();

        // Reset game state to initial values
        GameState::where('user_id', $userId)->update([
            'cash' => 1_000_000,
            'xp' => 0,
            'day' => 1
        ]);

        // Reset inventory (user-scoped inventories already exist)
        Inventory::where('user_id', $userId)->delete();
        // OR: Update to baseline quantities
        // Inventory::where('user_id', $userId)->update(['quantity' => 0]);
    });

    return redirect()->route('game.dashboard')
        ->with('success', 'Game reset successfully. Starting fresh at Day 1!');
}
```

#### **Step 2: Add Route**

**File:** `routes/web.php`

```php
Route::post('/game/reset', [GameController::class, 'resetGame'])
    ->name('game.reset')
    ->middleware(['auth', 'verified']);
```

#### **Step 3: Inventory Table Status (Already Addressed)**

**Current State:** `inventories` is already scoped by `user_id` with a unique constraint on (`user_id`, `location_id`, `product_id`).

**Reference Migration:** `database/migrations/2026_01_17_184437_add_location_id_to_orders_table.php`

#### **Step 4: Frontend Reset Button**

**File:** `resources/js/components/game/reset-game-button.tsx` (new)

```typescript
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

export function ResetGameButton() {
    const [showConfirm, setShowConfirm] = useState(false);
    const [isResetting, setIsResetting] = useState(false);

    const handleReset = () => {
        setIsResetting(true);
        router.post('/game/reset', {}, {
            onSuccess: () => {
                window.location.reload();  // Force full reload to clear cache
            },
            onError: () => {
                setIsResetting(false);
                setShowConfirm(false);
            }
        });
    };

    return (
        <>
            <Button
                onClick={() => setShowConfirm(true)}
                variant="destructive"
                disabled={isResetting}
            >
                {isResetting ? 'Resetting...' : 'Reset Game'}
            </Button>

            <ConfirmDialog
                open={showConfirm}
                onClose={() => setShowConfirm(false)}
                onConfirm={handleReset}
                title="Reset Game Progress?"
                message="This will permanently delete ALL your progress and restart at Day 1 with $10,000. This action cannot be undone."
                confirmText="Reset Game"
                cancelText="Cancel"
                variant="destructive"
            />
        </>
    );
}
```

#### **Step 5: Confirmation Dialog Component**

**File:** `resources/js/components/ui/confirm-dialog.tsx` (new)

```typescript
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface ConfirmDialogProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
}

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'default'
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-gray-600">{message}</p>
                <DialogFooter>
                    <Button onClick={onClose} variant="outline">
                        {cancelText}
                    </Button>
                    <Button onClick={onConfirm} variant={variant}>
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

#### **Step 6: UI Integration**

**File:** `resources/js/Pages/game/dashboard.tsx`

```typescript
import { ResetGameButton } from '@/components/game/reset-game-button';

export default function Dashboard() {
    return (
        <div className="container mx-auto p-6">
            {/* Existing dashboard content */}

            {/* Settings panel or debug menu */}
            <div className="mt-8 border-t pt-4">
                <h3 className="text-lg font-semibold mb-2">Game Settings</h3>
                <ResetGameButton />
            </div>
        </div>
    );
}
```

#### **Step 7: Feature Test**

**File:** `tests/Feature/ResetGameTest.php` (new)

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\GameState;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_clears_all_game_data(): void
    {
        $user = User::factory()->create();

        // Create game state with progress
        $gameState = GameState::create([
            'user_id' => $user->id,
            'cash' => 500_000,
            'xp' => 5000,
            'day' => 15
        ]);

        // Create some game history
        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Transfer::factory()->count(2)->create(['user_id' => $user->id]);
        Alert::factory()->count(5)->create(['user_id' => $user->id]);

        // Perform reset
        $response = $this->actingAs($user)->post('/game/reset');

        // Assert redirect
        $response->assertRedirect('/game/dashboard');

        // Assert game state reset
        $gameState->refresh();
        $this->assertEquals(1_000_000, $gameState->cash);
        $this->assertEquals(0, $gameState->xp);
        $this->assertEquals(1, $gameState->day);

        // Assert all data cleared
        $this->assertEquals(0, Order::where('user_id', $user->id)->count());
        $this->assertEquals(0, Transfer::where('user_id', $user->id)->count());
        $this->assertEquals(0, Alert::where('user_id', $user->id)->count());
    }

    public function test_reset_only_affects_current_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        GameState::create(['user_id' => $user1->id, 'cash' => 500_000, 'day' => 10]);
        GameState::create(['user_id' => $user2->id, 'cash' => 700_000, 'day' => 20]);

        Order::factory()->create(['user_id' => $user1->id]);
        Order::factory()->create(['user_id' => $user2->id]);

        // User 1 resets
        $this->actingAs($user1)->post('/game/reset');

        // User 1's data reset
        $this->assertEquals(1, GameState::find($user1->id)->day);
        $this->assertEquals(0, Order::where('user_id', $user1->id)->count());

        // User 2's data unchanged
        $this->assertEquals(20, GameState::find($user2->id)->day);
        $this->assertEquals(1, Order::where('user_id', $user2->id)->count());
    }

    public function test_guest_cannot_reset_game(): void
    {
        $response = $this->post('/game/reset');
        $response->assertRedirect('/login');
    }
}
```

### 6.3 Optional Enhancements

#### **A. Reset Telemetry**

Track reset events for analytics:

**Model:** `app/Models/ResetEvent.php`

```php
Schema::create('reset_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('final_day');
    $table->bigInteger('final_cash');
    $table->integer('final_xp');
    $table->integer('total_orders');
    $table->integer('total_transfers');
    $table->timestamp('reset_at');
});
```

**Usage:**
```php
ResetEvent::create([
    'user_id' => $userId,
    'final_day' => $gameState->day,
    'final_cash' => $gameState->cash,
    'final_xp' => $gameState->xp,
    'total_orders' => Order::where('user_id', $userId)->count(),
    'total_transfers' => Transfer::where('user_id', $userId)->count(),
    'reset_at' => now()
]);
```

#### **B. Soft Reset (New Game+)**

Allow players to keep XP/level but reset day/cash:

```php
public function softReset(Request $request)
{
    DB::transaction(function () {
        $userId = auth()->id();
        $gameState = GameState::where('user_id', $userId)->first();

        // Keep XP, reset everything else
        Order::where('user_id', $userId)->delete();
        Transfer::where('user_id', $userId)->delete();
        Alert::where('user_id', $userId)->delete();

        $gameState->update([
            'cash' => 1_000_000,
            'day' => 1
            // xp preserved!
        ]);
    });
}
```

#### **C. Export Save Data Before Reset**

Allow players to download their progress as JSON:

```php
public function exportSaveData(Request $request)
{
    $userId = auth()->id();

    $saveData = [
        'game_state' => GameState::where('user_id', $userId)->first(),
        'orders' => Order::with('items')->where('user_id', $userId)->get(),
        'transfers' => Transfer::where('user_id', $userId)->get(),
        'alerts' => Alert::where('user_id', $userId)->get(),
        'exported_at' => now()->toIso8601String()
    ];

    return response()->json($saveData)
        ->header('Content-Disposition', 'attachment; filename="moonshine-save-' . now()->format('Y-m-d') . '.json"');
}
```

### 6.4 Effort Estimation

| Task | Files | Complexity | Time |
|------|-------|------------|------|
| Backend reset logic | 1 | Low | 20 min |
| Route definition | 1 | Trivial | 5 min |
| Frontend button component | 1 | Low | 30 min |
| Confirmation dialog | 1 | Low | 20 min |
| UI integration | 1 | Trivial | 10 min |
| Feature tests | 1 | Low | 45 min |
| **TOTAL** | **6 files** | **Low** | **~1.5 hours** |

### 6.5 Risks & Blockers

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Cascade delete failures** | Medium (orphaned data) | Verify foreign key constraints |
| **Cache invalidation** | Low (stale data shown) | Force full page reload after reset |
| **Race conditions** | Low (concurrent resets) | Use database transaction |
| **Accidental resets** | High (user frustration) | Require explicit confirmation dialog |

---

## 7. Key Files Reference

### 7.1 Backend Files

| File | Purpose | Lines of Interest |
|------|---------|-------------------|
| `app/Models/GameState.php` | Game state model | Full file |
| `app/Services/SimulationService.php` | Day advancement engine | 24-100 (three-phase tick) |
| `app/Http/Controllers/GameController.php` | API endpoints | `advanceDay()`, `placeOrder()`, `createTransfer()` |
| `app/Providers/GameServiceProvider.php` | Service bindings + event wiring | Full file |
| `app/Actions/InitializeNewGame.php` | Per-user game bootstrapping | Full file |
| `app/Http/Middleware/HandleInertiaRequests.php` | State sharing | 43-78 (share method) |
| `database/migrations/2026_01_16_*_create_game_states_table.php` | Schema definition | Full file |

### 7.2 Frontend Files

| File | Purpose | Lines of Interest |
|------|---------|-------------------|
| `resources/js/contexts/game-context.tsx` | React state management | Full file |
| `resources/js/components/game/day-counter.tsx` | Day display UI | 9 (totalDays prop) |
| `resources/js/components/game/advance-day-button.tsx` | Day advancement trigger | Full file |
| `resources/js/types/index.d.ts` | TypeScript interfaces | `GameStateShared` type |
| `resources/js/Pages/game/dashboard.tsx` | Main game hub | Full file |

### 7.3 Database Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `game_states` | Core game state | `user_id`, `cash`, `xp`, `day` |
| `orders` | Purchase decisions | `user_id`, `vendor_id`, `total_cost`, `delivery_day` |
| `order_items` | Order line items | `order_id`, `product_id`, `quantity` |
| `transfers` | Inventory movements | `user_id`, `source_location_id`, `target_location_id` |
| `spike_events` | Demand spikes | `user_id`, `starts_at_day`, `ends_at_day`, `is_active`, `is_guaranteed` |
| `alerts` | System notifications | `user_id`, `type`, `severity`, `is_read` |
| `inventories` | Stock levels | `user_id`, `location_id`, `product_id`, `quantity` |

---

## 8. Gameplay Loop Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        PLAYER ACTION                         │
│                    (Click "Next Day")                        │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                    DATABASE TRANSACTION                      │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  DAY INCREMENT                                       │  │
│  │  └─ gameState->increment('day')                      │  │
│  └──────────────────────────────────────────────────────┘  │
│                             │                                │
│                             ▼                                │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  PHASE 1: EVENT TICK                                 │  │
│  │  ├─ End expired spikes (ends_at_day <= current_day)  │  │
│  │  ├─ Ensure guaranteed spike for today                │  │
│  │  ├─ Start scheduled spikes (starts_at_day <= day)    │  │
│  │  └─ Schedule optional future spike                   │  │
│  └──────────────────────────────────────────────────────┘  │
│                             │                                │
│                             ▼                                │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  PHASE 2: PHYSICS TICK                               │  │
│  │  ├─ Complete transfers (delivery_day <= current_day) │  │
│  │  ├─ Deliver orders (delivery_day <= current_day)     │  │
│  │  ├─ Inventory updates via delivery listeners         │  │
│  │  ├─ Apply storage costs (TimeAdvanced)               │  │
│  │  └─ Decay perishables (TimeAdvanced)                 │  │
│  └──────────────────────────────────────────────────────┘  │
│                             │                                │
│                             ▼                                │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  PHASE 3: ANALYSIS TICK                              │  │
│  │  ├─ Detect isolated locations                        │  │
│  │  ├─ Generate isolation alerts                        │  │
│  │  ├─ Recalculate KPIs (cash flow, inventory value)    │  │
│  │  └─ Generate suggested actions                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                    INERTIA MIDDLEWARE                        │
│                  (Share Updated Game State)                  │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                     REACT FRONTEND                           │
│  ├─ Update day counter display                              │
│  ├─ Show new alerts                                          │
│  ├─ Update cash/XP/level                                     │
│  ├─ Refresh inventory view                                   │
│  └─ Display completed orders/transfers                       │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. Answers Summary

### Q1: What is the initial state of the gameplay loop?
**Answer:** Players start at Day 1 with $10,000 cash, 0 XP, level 1, 85 reputation, and 0 strikes. The world graph (locations, routes), vendors, and products are pre-seeded. Per-user state includes baseline inventory, 1 shipped order (arrives Day 3), 2 in-transit transfers (arrive Days 2 and 4), and 3-5 initial spikes scheduled for Days 2-7. Alerts start empty.

### Q2: How does the gameplay loop evolve after Day 1?
**Answer:** Player clicks "Next Day" → day increments → three-phase tick (events, physics, analysis) runs. Spikes activate/end, orders/transfers complete, inventory updates via listeners, and alerts generate. The loop is player-initiated with no automatic progression.

### Q3: How should the gameplay loop resolve? Is it hard-capped at Day 7?
**Answer:** **No end condition exists.** The game is designed for indefinite sandbox play. The Day 30 counter is cosmetic UI only—players can advance beyond it. No day cap, game-over state, or victory/defeat conditions are implemented.

### Q4: What logic tracks player decisions?
**Answer:** PostgreSQL tables with `user_id` foreign keys:
- **Orders:** Purchase decisions (vendor choice, item selection, delivery timing)
- **Transfers:** Inventory movements (source/target locations, quantities)
- **Alerts:** System event acknowledgements (read/unread status)
- **Future:** Policy changes (inventory strategies)

All actions are queryable for history/analytics.

### Q5: If a "Reset" feature is implemented, how much of the codebase would need to be changed?
**Answer:** **6 files, ~1-2 hours effort.** No schema blocker now that `inventories` is user-scoped. Implementation is straightforward cleanup in a transaction with a confirmation dialog UI.

---

## 10. Recommendations

### 10.1 For Current Design (Indefinite Play)
1. ✅ **Keep sandbox model** - aligns with simulation genre conventions
2. ✅ **Add prestige system** - unlock perks after reaching milestones (Day 30, $100k cash, Level 10)
3. ✅ **Implement scenarios** - optional challenges with victory conditions (e.g., "Survive 10 days with 3 active spikes")
4. ✅ **Add save slots** - allow multiple parallel playthroughs

### 10.2 For Day Limit Implementation
1. ⚠️ **Add configurable day cap** - let players choose 7/14/30/unlimited
2. ⚠️ **Create end-game scoring** - rank performance (cash earned, alerts resolved, reputation)
3. ⚠️ **Implement New Game+** - restart with XP/unlocks preserved
4. ⚠️ **Add story mode** - narrative campaign with day-gated progression

### 10.3 For Reset Feature
1. ✅ **Implement full reset** - priority for testing/development
2. ✅ **Add soft reset** - keep XP for progression retention
3. ✅ **Export save data** - let players backup before reset
4. ✅ **Track reset events** - analytics for game balance tuning

---

## Appendices

### Appendix A: Related Documentation
- `technical-design-document.md` - Full system architecture
- `game-state-persistence-brainstorm.md` - State management patterns
- `guaranteed-spike-generation-plan.md` - Event system design
- `daily-reporting-infrastructure-analysis.md` - Analytics implementation

### Appendix B: Testing Commands
```bash
# Run full test suite
php artisan test

# Run specific test file
php artisan test tests/Feature/ResetGameTest.php

# Test day advancement
php artisan tinker
>>> $user = User::first();
>>> auth()->login($user);
>>> app(SimulationService::class)->advanceTime();
```

### Appendix C: Database Queries for Analysis
```sql
-- User progression snapshot
SELECT user_id, day, cash, xp, (xp / 1000 + 1) as level
FROM game_states
ORDER BY day DESC;

-- Financial history
SELECT user_id, SUM(total_cost) as total_spent, COUNT(*) as order_count
FROM orders
WHERE status = 'Delivered'
GROUP BY user_id;

-- Alert response metrics
SELECT user_id,
       COUNT(*) as total_alerts,
       COUNT(CASE WHEN is_read THEN 1 END) as read_alerts,
       AVG(EXTRACT(EPOCH FROM (read_at - created_at)) / 3600) as avg_hours_to_read
FROM alerts
GROUP BY user_id;
```

---

**Document Version:** 1.0
**Last Updated:** 2026-01-19
**Author:** AI Analysis Agent
**Review Status:** Pending stakeholder review
