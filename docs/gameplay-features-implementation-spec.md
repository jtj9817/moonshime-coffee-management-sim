# Gameplay Features Implementation Specification

**Date:** 2026-02-03
**Status:** Draft / Planning
**Based on:** `docs/gameplay-loop-analysis-and-improvements.md`
**Reference:** `docs/gameplay-loop-mechanics-analysis.md`

---

## 1. Overview & Purpose

This document provides the technical specifications and detailed implementation steps for the gameplay improvements outlined in the "Gameplay Loop Analysis" document. While the analysis identified *what* needs to happen to fix engagement, this document defines *how* to build it code-wise.

It is organized by the phases defined in the analysis roadmap, prioritizing critical fixes and "quick wins" that establish the feedback loops necessary for a management simulation.

---

## 2. Phase 0: Critical Architecture Fixes

**Objective:** Stabilize the economy and data isolation before adding new features.

### 2.1 Starting Cash Correction
**Problem:** `InitializeNewGame` uses `10000.00` (float) which `GameState` casts to `10000` (int/cents) = $100.
**Target State:** `1000000` (int/cents) = $10,000.

**Implementation Details:**
- **File:** `app/Actions/InitializeNewGame.php`
- **File:** `app/Http/Middleware/HandleInertiaRequests.php` (fallback creation logic)
- **Validation:** Create a test case `tests/Feature/GameInitializationTest.php` that asserts `GameState::first()->cash === 1000000`.

### 2.2 User Scoping Enforcement
**Problem:** Middleware queries for Alerts/Reputation do not filter by `user_id`.
**Target State:** All queries strictly scoped to `Auth::id()`.

**Implementation Details:**
- **File:** `app/Http/Middleware/HandleInertiaRequests.php`
- **Logic:**
    ```php
    // Reputation Calc
    $userId = $request->user()->id;
    $alertCount = Alert::where('user_id', $userId)->where('is_read', false)->count();
    
    // Strikes Calc
    $strikes = Alert::where('user_id', $userId)
        ->where('is_read', false)
        ->where('severity', 'critical')
        ->count();
    ```

---

## 3. Phase 1: Visibility & Consequences (The "Quick Wins")

**Objective:** Expose the hidden simulation mechanics (consumption) to the player and punish negligence.

### 3.1 Demand Forecasting Engine
Currently, players fly blind. We need a service to project future inventory states.

**New Service:** `app/Services/DemandForecastService.php`

```php
class DemandForecastService
{
    /**
     * Returns array of daily projections for a specific SKU at a location
     * [
     *   'day_offset' => 1, // Tomorrow
     *   'predicted_demand' => 5,
     *   'predicted_stock' => 25,
     *   'risk_level' => 'low'|'medium'|'stockout'
     * ]
     */
    public function getForecast(Inventory $inventory, int $days = 7): array
    {
        // 1. Get base consumption for product
        // 2. Get active spikes affecting this product/location
        // 3. Get incoming shipments (Orders/Transfers) arriving in next $days
        // 4. Simulate day-by-day decrement
    }
}
```

**Frontend Component:** `resources/js/components/inventory/DemandForecastChart.tsx`
- Use Recharts (or similar) to show a line graph:
    - Line 1: Projected Stock Level
    - Reference Line: Zero (Stockout)
    - Bar: Incoming Deliveries (on specific days)

### 3.2 Stockout & Lost Sales Tracking
Currently, stockouts just stop depletion. We need to track the economic damage.

**Database Schema:** `lost_sales`
```php
Schema::create('lost_sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('location_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->integer('day');
    $table->integer('quantity_lost'); // Amount demanded but not served
    $table->bigInteger('potential_revenue_lost'); // cents (quantity * unit_price)
    $table->timestamps();
    
    // Index for frequent reporting queries
    $table->index(['user_id', 'day']); 
});
```

**Service Logic Update:** `app/Services/DemandSimulationService.php`
- Inside the depletion loop, if `consumption > inventory`:
    1. Calculate `missed = consumption - inventory`.
    2. Create `LostSales` record.
    3. Dispatch event `StockoutOccurred` (for alerts).

### 3.3 Daily Summary Notification
Players need to know what happened while they "slept" (between turns).

**Logic:**
- On `advanceDay`, aggregate:
    - Total units sold.
    - Total lost sales.
    - Cash deducted (storage fees).
- Create a "Daily Summary" Alert (type: `info` or `summary`) containing this data in `metadata`.
- Frontend: Display this summary prominently on the Dashboard after day advance.

### 3.4 Financial Granularity (P&L per Location)
To make the game strategic, players need to see which locations are profitable and which are bleeding cash.

**Database Schema:** `location_daily_metrics`
```php
Schema::create('location_daily_metrics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('location_id')->constrained();
    $table->integer('day');
    
    // The P&L Breakdown (stored in cents)
    $table->bigInteger('revenue')->default(0);      // Units Sold * Price
    $table->bigInteger('cogs')->default(0);         // Cost of Goods Sold (Units * Avg Cost)
    $table->bigInteger('opex')->default(0);         // Operating Expenses (Rent + Storage + Staffing)
    $table->bigInteger('net_profit')->default(0);   // Revenue - COGS - OpEx
    
    // Operational Metrics
    $table->integer('units_sold')->default(0);
    $table->integer('stockouts')->default(0);
    $table->integer('satisfaction')->default(100);  // 0-100 score
    
    $table->timestamps();
    $table->index(['user_id', 'day']);
});
```

**Service Logic Update:** `app/Services/SimulationService.php`
- During the **Analysis Tick**, calculate and save these metrics for every active location.
- **OpEx Calculation:** `Location::rent` + (`Inventory::quantity` * `storage_cost`).

### 3.5 Pricing Strategy (The Player's Lever)
Players need a way to influence the "Revenue" side of the P&L equation.

**Database Schema:** Add to `locations` table or new `location_policies`
```php
// Add to locations table via migration
$table->integer('sell_price')->default(350); // 350 cents = $3.50 per cup
```

**Service Logic Update:** `app/Services/DemandSimulationService.php`
- **Price Elasticity:** Demand should react to price.
- Formula: `EffectiveDemand = BaseDemand * (StandardPrice / CurrentPrice)^ElasticityFactor`.
- If player raises price to $5.00, demand drops, but margin increases.
- If player lowers price to $2.00, demand spikes (good for clearing stock).

---

## 4. Phase 2: Core Engagement & Progression

**Objective:** Give players goals beyond "don't die".

### 4.1 Quest System Architecture
Static quests are insufficient. We need a system that can trigger based on state.

**Database Schema:** `quests`
```php
Schema::create('quests', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique(); // e.g., 'tutorial_first_order'
    $table->string('title');
    $table->text('description');
    $table->string('trigger_class'); // Class responsible for checking completion
    $table->json('trigger_params')->nullable();
    $table->json('rewards'); // ['cash' => 50000, 'xp' => 100]
    $table->timestamps();
});

Schema::create('user_quests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('quest_id')->constrained();
    $table->enum('status', ['active', 'completed', 'claimed']);
    $table->integer('progress')->default(0);
    $table->integer('target')->default(1);
    $table->timestamps();
});
```

**Service:** `app/Services/QuestService.php`
- `checkTriggers(User $user, string $eventContext)`: Called by listeners (e.g., `OrderPlaced`, `DayAdvanced`).
- Loads active `UserQuests`.
- Instantiates `trigger_class` (implements `QuestTriggerInterface`).
- If condition met, update status to `completed` and dispatch notification.

**Example Triggers:**
- `PlaceOrderTrigger`: Checks if `Order::where('user_id', $user->id)->exists()`.
- `SurviveSpikeTrigger`: Checks if spike ended without stockouts.

### 4.2 Active Spike Resolution
Spikes shouldn't just be "weather". Players should be able to fight back.

**Database Schema:** `spike_resolutions`
```php
Schema::create('spike_resolutions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('spike_event_id')->constrained();
    $table->string('action_type'); // e.g., 'pay_expedite', 'marketing_campaign'
    $table->bigInteger('cost');
    $table->json('effects_applied'); // e.g., {'duration_reduction': 2}
    $table->timestamps();
});
```

**Controller Action:** `POST /game/spikes/{spike}/resolve`
- Accepts `action_type`.
- Validates player has resources (Cash/Reputation).
- Applies logic:
    - **Expedite:** `spike->decrement('ends_at_day', 2)`.
    - **Marketing:** Reduces demand multiplier in `spike->metadata`.
- Records resolution.

---

## 5. Phase 3: Strategic Planning Tools

**Objective:** Enable deep gameplay.

### 5.1 "What-If" Scenario Calculator
**Frontend Only Feature (initially):**
- A specialized calculator component `ScenarioPlanner.tsx`.
- Inputs: Current Stock, Daily Consumption (Avg), Lead Time, Reorder Point.
- Output: "You will stock out in X days. You need to order Y units to survive Z days."
- Uses `DemandForecastService` logic (ported to JS or via API endpoint).

### 5.2 Bulk Order Scheduler
**Feature:** "Repeat this order every 7 days".

**Database Schema:** `scheduled_orders`
- Similar to `orders` but with `cron_expression` or `interval_days`.
- **Service:** `ScheduledOrderService` runs on `DayAdvanced`.
- Checks active schedules.
- Creates actual `Order` (Draft or Pending).
- If "Auto-Submit" is enabled (Level 5+ feature?), deducts cash and submits.

---

## 6. Implementation Rules & Standards

1.  **Money is Integers:** All new monetary columns MUST be `bigInteger` (cents). Frontend formatting only.
2.  **Logic in Services:** Controllers should only parse requests and call Services.
3.  **Inertia for Everything:** No pure API endpoints unless absolutely necessary for async charting. Use `HandleInertiaRequests` for global state and Page Props for specific data.
4.  **Testing:** Every new Service method requires a Unit Test. Every Controller action requires a Feature Test.

---

## 7. Immediate Next Steps (Day 1)

1.  Create migration to fix `game_states` default (if needed) or just fix the `InitializeNewGame` action.
2.  Apply Middleware scoping fixes.
3.  Scaffold `LostSales` migration and model.
4.  Update `DemandSimulationService` to populate `LostSales`.
