# Gameplay Loop Analysis and Improvement Proposals

**Date:** 2026-01-24
**Status:** Analysis Complete
**Context:** Current session codebase review

---

## Executive Summary

This document analyzes the current gameplay loop implementation in Moonshine Coffee Management Sim, identifies discrepancies between documented and actual behavior, and proposes specific improvements to make the game more engaging. The game has solid infrastructure but lacks player motivation, visible stakes, and meaningful progression systems.

---

## Current Gameplay Loop Implementation

### Day One Initial State

**Starting Conditions:**
- **Cash:** **BUG**: Currently $100.00 (should be $10,000.00)
  - Schema default: `1000000` cents = $10,000.00 (`database/migrations/2026_01_16_055234_create_game_states_table.php:17`)
  - Code initializes: `10000.00` which stores as `10000` cents = $100.00 ❌
  - **Files with bug**: `InitializeNewGame.php:41`, `HandleInertiaRequests.php:107`
- **XP:** 0, **Level:** 1 (computed: `floor(xp / 1000) + 1`)
- **Reputation:** 85 (base 85 - 3 × unread alerts) - **BUG**: Not user-scoped
- **Strikes:** 0 (count of critical unread alerts) - **BUG**: Not user-scoped

**Pre-Seeded World:**
- **Locations:** Store graph with warehouse and secondary stores (via `GraphSeeder` + `CoreGameStateSeeder`)
- **Products:** Core SKUs (beans, milk, cups) with perishability flags
- **Vendors:** 3 suppliers with reliability scores
- **Routes:** Logistics graph connecting locations

**Per-User Game State:**
- **Primary Store:** 30 units perishables, 80 units non-perishables
- **Warehouse:** 20 units perishables, 200 units non-perishables
- **Secondary Stores:** 10 units perishables, 25 units non-perishables
- **Pipeline Activity:**
  - 1 shipped order arriving Day 3 (via `LogisticsService::findBestRoute()`)
  - 2 in-transit transfers arriving Days 2 and 4
- **Spike Events:** 3-5 guaranteed spikes scheduled across Days 2-7 (via `SpikeSeeder`)
- **Alerts:** None initially

**User Experience on Day 1:**
- Welcome Banner displayed (condition: `day === 1 && !has_placed_first_order`)
- Mission Control Dashboard showing:
  - Location status cards with alert indicators
  - KPIs (Inventory Value, Low Stock Items, Pending Orders, Locations)
  - Active Quests (currently static placeholders)
  - Logistics health widget
- "Place First Order" CTA in Welcome Banner
- No time pressure or immediate threats

### Daily Advancement System (4-Phase Tick)

When player clicks "Next Day" button (`POST /game/advance-day`), `SimulationService::advanceTime()` executes these phases inside a database transaction:

#### Phase 1: Event Tick
**File:** `app/Services/SimulationService.php` (lines 45-78)

Actions:
1. End expired spikes: `ends_at_day <= current_day`
   - Updates `is_active: false`, `resolved_at: now()`, `resolved_by: 'time'`
   - Dispatches `SpikeEnded` event
2. Ensure guaranteed spike coverage for today (after Day 1)
3. Start scheduled spikes: `starts_at_day <= current_day` and `ends_at_day > current_day`
   - Updates `is_active: true`
   - Dispatches `SpikeOccurred` event
   - Records cooldown via `SpikeConstraintChecker`
4. Optionally schedule future spike when constraints allow
   - Checks caps (max 2 concurrent), cooldowns (2 days per type)
   - Uses weighted type selection via `SpikeEventFactory`

#### Phase 2: Physics Tick
**File:** `app/Services/SimulationService.php` (lines 84-104)

Actions:
1. Complete transfers: `status = InTransit` and `delivery_day <= current_day`
   - Transitions to `Completed` state
2. Deliver orders: `status = Shipped` and `delivery_day <= current_day`
   - Transitions to `Delivered` state
3. Inventory updates happen via event listeners on delivery transitions

#### Phase 3: Consumption Tick
**File:** `app/Services/DemandSimulationService.php` (invoked from `SimulationService.php:161`)

**NOT DOCUMENTED IN ORIGINAL `gameplay-loop-mechanics-analysis.md`** - This phase was added after initial documentation but is critical to gameplay

**Purpose:** Simulates daily customer demand and inventory depletion at store locations.

**Detailed Flow:**
1. **Query Store Inventories** (lines 31-39)
   - Get all `Location::where('type', 'store')` with user's inventory
   - Eager load inventories with `->with(['inventories' => fn($q) => $q->where('user_id', $userId)->with('product')])`
   - **User-scoped**: Only depletes current user's inventory ✅

2. **Calculate Consumption per Product** (lines 44-68)
   - **Baseline Demand**: 5 units/day (from `$baselineConsumption['default']`)
   - **Random Variance**: ±20% (`rand(-20, 20) / 100`)
   - **Actual Formula**: `consumption = baseline * (1 + variance)`
   - Example: 5 units × 1.15 = 5.75 → rounds to `5` or `6` units

3. **Apply Demand Spike Multipliers** (lines 57-63)
   - Checks for active `type = 'demand'` spikes via `getDemandMultiplier()`
   - Spike matching logic:
     - Exact location match OR global (null `location_id`)
     - Exact product match OR global (null `product_id`)
   - Multiplier range: 1.5x - 3.0x (defined in spike metadata)
   - Final consumption: `base_consumption * variance * spike_multiplier`

4. **Deplete Inventory** (lines 65-72)
   - `$actualConsumed = min($consumption, $inventory->quantity)`
   - `$inventory->decrement('quantity', $actualConsumed)`
   - Cannot go below 0 (clamped)

5. **Stockout Detection** (lines 69-72)
   - If `consumption > actualConsumed`: stockout occurred
   - Logs warning: `"Stockout at {location} for product {id}"`
   - **Missing**: No event dispatched, no alert generated, no lost sales tracked
   - **Impact**: Stockouts are invisible to player unless they check inventory manually

**Key Observations:**
- This is the **primary driver of gameplay urgency**
- Runs every day after Day 1 (Day 1 has no consumption)
- Creates natural depletion requiring player to restock
- Spike system amplifies consumption, creating crisis moments

**Missing Features:**
- No stockout alerts (only logs)
- No lost sales tracking (revenue system doesn't exist)
- No customer satisfaction impact
- No demand forecasting (players can't predict consumption)

**Configuration Gaps:**
- Baseline consumption hardcoded (`5 units`) - should be in config or DB
- No per-product or per-category consumption profiles
- No time-of-day or seasonal demand variations

#### Phase 4: Analysis Tick
**File:** `app/Services/SimulationService.php` (lines 106-112)

Actions:
1. Detect isolated locations using BFS route checking via `LogisticsService::checkReachability()`
2. Generate isolation alerts **only if**:
   - Store is isolated (unreachable from supply chain)
   - AND store has low stock (`quantity < 10`)
   - AND no existing active isolation alert
3. Resolve isolation alerts when stores become reachable again
4. Spike cause detection for isolation alerts (blizzard, breakdown)

### Post-Tick Event Listeners

After phases complete, `TimeAdvanced` event is dispatched with listeners in `GameServiceProvider`:

| Listener | Purpose |
|-----------|---------|
| `DecayPerishables` | Decays perishable inventory by 5% daily |
| `ApplyStorageCosts` | Deducts `sum(quantity × storage_cost)` from cash |
| `CreateDailyReport` | Generates `DailyReport` for **previous day** (day - 1) |
| `SnapshotInventoryLevels` | Captures inventory snapshots for `inventory_history` table |

**Daily Report Contents:**
- Orders placed on previous day
- Spikes started/ended
- Alerts generated
- Transfers completed
- Current cash and XP

---

## Cash Handling Architecture

**Important**: The game uses a **cent-based monetary system** to avoid floating-point arithmetic errors.

### Storage and Display Flow

```
Database (bigInteger)     Laravel Backend           Frontend Display
─────────────────────    ───────────────────       ────────────────
1,000,000 cents    →     (float) 1000000.0    →    formatCurrency()
                                                    → "1,000,000.00"
                                                    → Display: $1,000,000.00
```

### Convention Throughout Codebase

**Storage (Database):**
- Column type: `bigInteger` (no decimals)
- Store all monetary values as **cents**
- Example: $10,000.00 = `1000000` cents

**Backend (PHP):**
- Use integers when creating/updating cash values
- Cast to float only when sending to frontend: `(float) $gameState->cash`
- Example: `['cash' => 1000000]` (NOT `10000.00`)

**Frontend (TypeScript/React):**
- Receive as float from Inertia props
- Display using `formatCurrency()` helper
- Calculation: Divide by 100 to show dollars: `10000.00 / 100 = $100.00`

**Key Files:**
- Migration: `database/migrations/2026_01_16_055234_create_game_states_table.php:17`
- Middleware: `app/Http/Middleware/HandleInertiaRequests.php:111`
- Formatter: `resources/js/lib/formatCurrency.ts`

### Current Bug Status

❌ **Bug**: `InitializeNewGame.php` and `HandleInertiaRequests.php` use `10000.00` instead of `1000000`
✅ **Correct**: Schema default is `1000000` (but gets overridden by buggy code)

## Discrepancies Between Documentation and Actual Code

| Area | Documented Behavior | Actual Implementation | Impact |
|------|-------------------|----------------------|--------|
| **Initial Cash** | 1,000,000 cents ($10,000) per schema | **BUG**: Code uses `10000.00` which stores as 10,000 cents = **$100.00** | **CRITICAL**: Players start with 100x less money than intended! |
| **Phase System** | 3 phases (Event, Physics, Analysis) | 4 phases (Consumption Tick added) | Critical difference - demand simulation now occurs daily |
| **Spike Initialization** | Hardcoded in `InitializeNewGame` | Uses `SpikeSeeder::seedInitialSpikes()` with constraints | Better implementation with cooldown/type diversity |
| **Daily Report Timing** | Generated same-day | Generated for **previous day** (day - 1) | Correct - reports on completed day |
| **Alert Resolution** | Not mentioned | Added `is_resolved` field to `Alert` model | Isolation alerts auto-resolve |
| **Isolation Alert Logic** | Generated for all isolated stores | Generated **only** for isolated stores with low stock (< 10 units) | Reduces noise - more focused alerts |
| **Welcome Banner** | Not mentioned | Shown on Day 1 when `!has_placed_first_order` | Good UX - guides first-time players |
| **Game Initialization** | Single pass | Includes idempotency check to prevent reseeding | Robust - handles refreshes gracefully |
| **Reputation Calculation** | Global unread alerts | Global (not user-scoped) | **BUG** - should be user-scoped |
| **End Condition** | Sandbox model only | Truly infinite sandbox - no end conditions | Aligns with design |

### Critical Bugs Identified

1. **CRITICAL: Starting Cash Mismatch** ⚠️⚠️⚠️
   ```php
   // database/migrations/2026_01_16_055234_create_game_states_table.php line 17
   $table->bigInteger('cash')->default(1000000); // $10,000.00 in cents

   // BUT...

   // app/Actions/InitializeNewGame.php line 41
   ['cash' => 10000.00, 'xp' => 0, 'day' => 1]  // BUG: Only 10,000 cents = $100.00!

   // app/Http/Middleware/HandleInertiaRequests.php line 107
   ['cash' => 10000.00, 'xp' => 0, 'day' => 1]  // Same bug
   ```

   **Impact**: Players start with **$100.00 instead of $10,000.00** - makes game nearly unplayable!

   **Root Cause**: Cash is stored as cents (bigInteger) in database, but code initializes with decimal value 10000.00 which becomes 10000 cents when stored.

   **Fix**:
   ```php
   // Both files should use:
   ['cash' => 1000000, 'xp' => 0, 'day' => 1]  // 1,000,000 cents = $10,000.00
   ```

   **Display Flow**:
   - Database stores: `10000` (bigInteger)
   - Middleware casts: `(float) $gameState->cash` → `10000.0`
   - Frontend displays: `formatCurrency(10000.0)` → `"10,000.00"`
   - User sees: `$10,000.00` but has actual value of `$100.00` in game logic!

2. **Reputation Calculation Not User-Scoped**
   ```php
   // app/Http/Middleware/HandleInertiaRequests.php line 138
   $alertCount = Alert::where('is_read', false)->count();  // BUG: no user_id filter!
   ```

   **Impact**: In multi-user scenarios, one player's reputation is affected by other players' alerts.

   **Fix**:
   ```php
   $alertCount = Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->count();
   ```

3. **Strikes Calculation Not User-Scoped**
   ```php
   // app/Http/Middleware/HandleInertiaRequests.php line 150
   return Alert::where('is_read', false)
       ->where('severity', 'critical')
       ->count();  // BUG: no user_id filter!
   ```

   **Fix**:
   ```php
   return Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->where('severity', 'critical')
       ->count();
   ```

4. **Global Alerts in Middleware**
   ```php
   // app/Http/Middleware/HandleInertiaRequests.php line 90
   'alerts' => Alert::where('is_read', false)
       ->latest()
       ->take(10)
       ->get(),  // BUG: no user_id filter!
   ```

   **Impact**: Players see each other's alerts in multi-user environments.

   **Fix**:
   ```php
   'alerts' => Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->latest()
       ->take(10)
       ->get(),
   ```

---

## Engagement Issues and Root Causes

### 1. Day 1 Passivity
**Problem:** No urgency or clear objective. Welcome banner suggests ordering but there's no penalty for inaction.

**Root Cause:**
- No time pressure mechanics
- Consumption hasn't started (only happens when day advances)
- No visible threat or motivation
- **Compounded by starting cash bug**: With only $100, players can barely afford one order, but UI doesn't warn them!

**Impact:**
- Players may click around without understanding core loop
- With cash bug, players will go broke after first order, creating confusion
- No indication that Day 1 is "safe" but Day 2+ will deplete inventory

### 2. Invisible Demand System
**Problem:** Consumption happens silently in background. Players don't see daily demand until inventory drops unexpectedly.

**Root Cause:**
- No UI showing predicted demand
- No stockout warnings before they happen
- Consumption is hidden behind event listeners

**Impact:** Game feels reactive rather than strategic.

### 3. No Meaningful Consequences
**Problem:** Stockouts only affect reputation score (a displayed number with no gameplay impact).

**Root Cause:**
- Reputation is cosmetic (not tied to mechanics)
- No lost sales tracking
- No customer satisfaction or loyalty systems
- Vendor relationships are static

**Impact:** Players can ignore stockouts without real penalty.

### 4. Spike System is Abstract
**Problem:** Spikes are "events" with playbook descriptions but no active resolution.

**Root Cause:**
- Spikes auto-resolve via time passage
- No player agency in spike resolution
- Playbook is informational only

**Impact:** Spikes feel like RNG rather than challenges to overcome.

### 5. Lack of Progression
**Problem:** XP/level system exists but does nothing.

**Root Cause:**
- Level doesn't unlock features
- No new mechanics introduced over time
- No prestige or new game+ systems
- Quests are static placeholders

**Impact:** Game lacks long-term motivation.

### 6. Analytics Not Actionable
**Problem:** Analytics page shows historical data but doesn't help players make decisions.

**Root Cause:**
- PrismAiService exists but isn't used effectively
- No predictions or forecasts
- No what-if scenarios
- Spike impact analysis is shallow

**Impact:** Players have data but no insights.

### 7. No Multi-Day Planning
**Problem:** Players can only react to current day, can't plan ahead.

**Root Cause:**
- No demand forecasting
- No cash flow projections
- No bulk order scheduling
- Multi-hop routing exists but no planning tools

**Impact:** Game becomes repetitive daily management without strategy.

---

## Improvement Proposals

### Priority 1: Fix Critical Bugs (MUST FIX FIRST!)

#### 1.1 Fix Starting Cash Bug ⚠️ **GAME-BREAKING**

**Files:**
- `app/Actions/InitializeNewGame.php`
- `app/Http/Middleware/HandleInertiaRequests.php`

**Changes:**
```php
// app/Actions/InitializeNewGame.php line 41
// Before:
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 10000.00, 'xp' => 0, 'day' => 1]  // BUG: Only $100!
);

// After:
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 1000000, 'xp' => 0, 'day' => 1]  // Correct: 1M cents = $10,000
);

// app/Http/Middleware/HandleInertiaRequests.php line 107
// Before:
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 10000.00, 'xp' => 0, 'day' => 1]  // BUG: Only $100!
);

// After:
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 1000000, 'xp' => 0, 'day' => 1]  // Correct: 1M cents = $10,000
);
```

**Effort:** 2 minutes
**Impact:** **CRITICAL** - Without this fix, players start with $100 instead of $10,000, making the game unplayable. Orders cost ~$100-500, so players can only place 1 order before going broke.

**Testing:**
```bash
# After fix, reset game and verify starting cash
php artisan tinker
>>> $user = User::first();
>>> $user->gameState()->delete();
>>> app(InitializeNewGame::class)->handle($user);
>>> $user->gameState->cash; // Should be 1000000 (not 10000)
```

#### 1.2 Fix User Scoping Bugs in Middleware

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

**Changes:**
```php
// Fix 1: Reputation calculation (line 138)
// Before:
$alertCount = Alert::where('is_read', false)->count();

// After:
$alertCount = Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->count();

// Fix 2: Strikes calculation (line 150)
// Before:
return Alert::where('is_read', false)
    ->where('severity', 'critical')
    ->count();

// After:
return Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->where('severity', 'critical')
    ->count();

// Fix 3: Shared alerts (line 90)
// Before:
'alerts' => Alert::where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),

// After:
'alerts' => Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),
```

**Effort:** 5 minutes
**Impact:** Fixes multiplayer data leakage - ensures each player only sees their own alerts, reputation, and strikes

---

### Priority 2: Make Consumption Visible and Impactful

#### 2.1 Add Demand Forecasting to Inventory Pages

**Implementation:**
- Add `DemandForecastService` that predicts consumption for next 7-14 days
- Show "Projected Consumption: X-Y units/day" on SKU detail pages
- Display "Days until stockout" based on current inventory vs. projected demand
- Color-code warnings (green >7 days, yellow 3-7 days, red <3 days)

**Files to Create:**
- `app/Services/DemandForecastService.php`
- `resources/js/components/inventory/demand-forecast-card.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add forecast data to `skuDetail()`
- `resources/js/pages/game/sku-detail.tsx` - display forecast

**Effort:** 4-6 hours
**Impact:** Makes game strategic rather than reactive

#### 2.2 Add Stockout Consequences

**Implementation:**
- Track missed sales when `demand > inventory` in `DemandSimulationService`
- Create `LostSales` model to record opportunity cost
- Display "Lost Sales Today: $X" on dashboard
- Reduce customer satisfaction (new metric) on stockouts
- Satisfied customers increase future demand, dissatisfied decrease

**Files to Create:**
- `database/migrations/YYYY_MM_DD_create_lost_sales_table.php`
- `app/Models/LostSales.php`
- `app/Models/CustomerSatisfaction.php`

**Files to Modify:**
- `app/Services/DemandSimulationService.php` - record stockouts
- `app/Http/Controllers/GameController.php` - add lost sales to KPIs
- `app/Actions/InitializeNewGame.php` - seed customer satisfaction

**Effort:** 6-8 hours
**Impact:** Adds stakes to inventory management

---

### Priority 3: Add Meaningful Early Game Goals

#### 3.1 Implement Tutorial Quests

**Implementation:**
- Create dynamic quest system with conditions
- Day 1 Quest: "Place your first order before Day 2"
- Day 2 Quest: "Complete first transfer to secondary store"
- Day 3 Quest: "Survive first demand spike without stockouts"

**Files to Create:**
- `app/Models/Quest.php`
- `database/migrations/YYYY_MM_DD_create_quests_table.php`
- `app/Services/QuestService.php`
- `app/Listeners/UpdateQuestProgress.php` - listens to game events

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - replace static quests with `QuestService`
- `resources/js/components/game/QuestCard.tsx` - add progress tracking

**Effort:** 8-10 hours
**Impact:** Guides new players, adds early-game direction

#### 3.2 Add Time Pressure to Day 1

**Implementation:**
- Show warning: "Current inventory will last X days at projected demand"
- Countdown timer: "First orders arriving in Y days"
- Emphasize urgency in Welcome Banner copy

**Files to Modify:**
- `resources/js/components/game/welcome-banner.tsx` - add urgency messaging
- `app/Services/DemandForecastService.php` (from 2.1) - calculate initial stockout risk

**Effort:** 2 hours
**Impact:** Motivates immediate engagement

---

### Priority 4: Improve Spike System

#### 4.1 Add Active Spike Resolution

**Implementation:**
- Add resolution actions for each spike type:
  - **Delay Spike:** "Hire emergency delivery contractor" ($500, reduces delay by 1 day)
  - **Blizzard Spike:** "Divert route via secondary path" (reroutes logistics)
  - **Breakdown Spike:** "Emergency repairs" ($200, restores capacity faster)
  - **Demand Spike:** "Price increase promotion" (reduces demand by 20%, loses some sales)
  - **Price Spike:** "Negotiate bulk discount" (reduces cost impact by 15%)
- Track `resolved_by: 'player'` when player takes action
- Award XP for proactive resolution

**Files to Create:**
- `app/Services/SpikeResolutionService.php`
- `database/migrations/YYYY_MM_DD_add_spike_resolutions_table.php`
- `app/Models/SpikeResolution.php`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add `resolveSpike()` endpoint
- `resources/js/pages/game/spike-history.tsx` - add action buttons
- `app/Services/SimulationService.php` - apply resolution effects

**Effort:** 12-15 hours
**Impact:** Makes spikes active challenges rather than passive events

#### 4.2 Add Spike Aftermath

**Implementation:**
- Add recovery period (2-3 days) after spike ends
- During recovery: demand suppressed by 10-20% (customers wary)
- Display "Recovery in progress: X days remaining"
- Award "Resilience" bonus if player managed stockouts well

**Files to Modify:**
- `app/Services/SimulationService.php` - handle recovery state
- `app/Models/SpikeEvent.php` - add `recovery_ends_at_day`

**Effort:** 4-6 hours
**Impact:** Adds strategic depth to spike management

---

### Priority 5: Add Progression and Unlockables

#### 5.1 Unlock New Locations at Levels

**Implementation:**
- Level 3: Unlock "Downtown Cafe" (high-demand location, higher rent)
- Level 5: Unlock "Airport Kiosk" (high prices, very limited storage)
- Level 7: Unlock "Office Building Hub" (bulk orders, low margin)
- Each location has unique characteristics (demand profile, storage, costs)

**Files to Modify:**
- `database/seeders/GraphSeeder.php` - mark new locations as locked
- `app/Models/Location.php` - add `required_level` column
- `app/Http/Controllers/GameController.php` - check level before allowing access
- `resources/js/components/inventory/LocationCard.tsx` - show lock icon if not unlocked

**Effort:** 6-8 hours
**Impact:** Provides long-term progression goals

#### 5.2 Unlock New Vendors via Reputation

**Implementation:**
- Reputation 90: Unlock "Premium Bean Co" (higher quality, higher price)
- Reputation 85: Unlock "Budget Supplies" (lower quality, lowest price)
- Reputation 70: Unlock "Rapid Delivery Inc." (fast shipping, unreliable)
- Vendor selection affects product quality and customer satisfaction

**Files to Modify:**
- `database/seeders/CoreGameStateSeeder.php` - add new vendors with reputation thresholds
- `app/Models/Vendor.php` - add `required_reputation` column
- `app/Http/Controllers/GameController.php` - filter vendors by reputation

**Effort:** 4-6 hours
**Impact:** Gives reputation meaning, adds strategic vendor selection

#### 5.3 Implement Prestige System

**Implementation:**
- Complete Day 30 with metrics (cash > $20k, reputation > 80, < 5 critical alerts)
- Earn "Prestige Points" for exceptional performance
- Prestige Points unlock:
  - Starting bonuses (more cash, better initial inventory)
  - New game modifiers (easier spikes, longer vendor lead times)
  - Cosmetics (store themes, badges)

**Files to Create:**
- `app/Models/PrestigeLevel.php`
- `database/migrations/YYYY_MM_DD_create_prestige_levels_table.php`
- `app/Services/PrestigeService.php`

**Files to Modify:**
- `app/Actions/InitializeNewGame.php` - apply prestige bonuses
- `resources/js/components/game/PrestigeBanner.tsx` - display prestige status

**Effort:** 8-10 hours
**Impact:** Adds replayability, gives completion incentive

---

### Priority 6: Make Analytics Actionable

#### 6.1 AI-Powered Recommendations

**Implementation:**
- Integrate existing `PrismAiService` to analyze patterns
- Generate daily suggestions:
  - "Product X is consistently stockouting - increase reorder point"
  - "Warehouse has excess inventory - consider transfer to secondary stores"
  - "Vendor A has 15% late deliveries - consider backup supplier"
- Display recommendations on dashboard and analytics page

**Files to Create:**
- `app/Services/AiRecommendationService.php`
- `database/migrations/YYYY_MM_DD_create_recommendations_table.php`
- `resources/js/components/analytics/RecommendationCard.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add recommendations to props
- `app/Listeners/CreateDailyReport.php` - trigger AI analysis

**Effort:** 10-12 hours
**Impact:** Turns data into insights, helps players improve

#### 6.2 What-If Scenarios

**Implementation:**
- Add scenario calculator on analytics page:
  - "What if we increase safety stock by 20%?" → projects cost vs. stockout risk
  - "What if we switch to JIT strategy?" → projects inventory savings vs. stockout risk
  - "What if we add a new location?" → projects ROI over 30 days
- Use Monte Carlo simulation for probabilistic forecasts

**Files to Create:**
- `app/Services/ScenarioAnalysisService.php`
- `resources/js/components/analytics/ScenarioCalculator.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add scenario endpoint
- `resources/js/pages/game/analytics.tsx` - add scenario tab

**Effort:** 12-15 hours
**Impact:** Enables strategic planning and experimentation

---

### Priority 7: Add Multi-Day Planning Tools

#### 7.1 Demand Forecast Charts

**Implementation:**
- Show 7-14 day demand forecast by product/location
- Include confidence intervals (based on variance ±20%)
- Overlay predicted inventory levels
- Highlight projected stockouts with warnings

**Files to Create:**
- `resources/js/components/analytics/DemandForecastChart.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add forecast data
- `resources/js/pages/game/analytics.tsx` - add forecast tab

**Effort:** 6-8 hours
**Impact:** Enables proactive inventory management

#### 7.2 Cash Flow Projections

**Implementation:**
- Project cash flow for next 7-14 days:
  - Upcoming order deliveries (outgoing cash)
  - Upcoming storage costs
  - Revenue from sales (add simple revenue model)
- Show "Project cash on Day X: $Y"
- Warn if projected cash < $0

**Files to Create:**
- `app/Services/CashFlowProjectionService.php`
- `resources/js/components/analytics/CashFlowChart.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add projections
- `resources/js/pages/game/analytics.tsx` - add cash flow section

**Effort:** 8-10 hours
**Impact:** Prevents bankruptcy, enables financial planning

#### 7.3 Bulk Order Scheduling

**Implementation:**
- Allow placing orders for future delivery dates
- Schedule weekly restocks in advance
- Show scheduled orders on timeline view
- Auto-alert if scheduled order won't arrive before stockout

**Files to Create:**
- `app/Models/ScheduledOrder.php`
- `database/migrations/YYYY_MM_DD_create_scheduled_orders_table.php`
- `resources/js/components/ordering/OrderScheduler.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add scheduling endpoints
- `app/Services/OrderService.php` - handle scheduled orders
- `resources/js/pages/game/ordering.tsx` - add scheduler UI

**Effort:** 10-12 hours
**Impact:** Reduces daily micromanagement, enables strategy

---

### Priority 8: Enhance Quest System

#### 8.1 Dynamic Quest Generation

**Implementation:**
- Generate quests based on current game state:
  - "Survive a demand spike without stockouts" (when demand spike active)
  - "Maintain 90%+ fulfillment rate for 7 days" (challenge quest)
  - "Reduce warehouse waste to <5 units" (optimization quest)
  - "Reach $X cash by Day Y" (progression quest)
- Quest rewards: XP, cash, reputation, prestige points

**Files to Create:**
- `app/Services/QuestGeneratorService.php`
- `app/Events/QuestCompleted.php`
- `app/Listeners/AwardQuestRewards.php`

**Files to Modify:**
- `app/Services/QuestService.php` - add dynamic generation
- `resources/js/components/game/QuestCard.tsx` - show quest types

**Effort:** 10-12 hours
**Impact:** Adds variety, adapts to player skill level

#### 8.2 Weekly Challenges and Leaderboards

**Implementation:**
- Weekly challenge: "Profit Challenge" - maximize profit in 7 days
- Leaderboards show top performers (local only for now)
- Completion awards badges/achievements
- Rotate challenge types (speed, efficiency, resilience)

**Files to Create:**
- `app/Models/WeeklyChallenge.php`
- `database/migrations/YYYY_MM_DD_create_weekly_challenges_table.php`
- `resources/js/components/game/Leaderboard.tsx`

**Files to Modify:**
- `app/Http/Controllers/GameController.php` - add challenge endpoints
- `resources/js/pages/game/dashboard.tsx` - show active challenge

**Effort:** 12-15 hours
**Impact:** Adds competitive element, replayability

---

### Priority 9: Add Endgame Scenarios

#### 9.1 Optional Day Limits

**Implementation:**
- Add game mode selection at start:
  - "Sandbox" (unlimited, current mode)
  - "7-Day Sprint" (complete day 7 with best metrics)
  - "14-Day Challenge" (survive 2 weeks with challenges)
  - "30-Day Marathon" (build sustainable business)
- Show endgame scorecard with rankings:
  - Final cash
  - Total revenue
  - Reputation score
  - Stockouts avoided
  - Spikes managed

**Files to Create:**
- `app/Models/GameMode.php`
- `database/migrations/YYYY_MM_DD_create_game_modes_table.php`
- `resources/js/pages/game/game-over.tsx`

**Files to Modify:**
- `app/Actions/InitializeNewGame.php` - add mode selection
- `app/Services/SimulationService.php` - check end conditions
- `app/Http/Controllers/GameController.php` - add endgame handling

**Effort:** 10-12 hours
**Impact:** Gives closure, enables speedrunning, appeals to completionists

#### 9.2 Scenario Modes

**Implementation:**
- Predefined scenarios with specific challenges:
  - "Turnaround": Take over failing store with low inventory, high debt
  - "Crisis Management": 3 concurrent spikes on Day 1
  - "Expansion": Start with warehouse, build 3 new stores
  - "Blizzard Survival": Winter scenario with constant route disruptions
- Scenario completion unlocks badges

**Files to Create:**
- `app/Models/Scenario.php`
- `database/migrations/YYYY_MM_DD_create_scenarios_table.php`
- `database/seeders/ScenarioSeeder.php`

**Files to Modify:**
- `app/Actions/InitializeNewGame.php` - load scenario config
- `resources/js/components/game/ScenarioSelector.tsx`

**Effort:** 15-20 hours
**Impact:** Adds narrative variety, tests different skill sets

---

## Implementation Roadmap

### Phase 0: Critical Fixes (IMMEDIATE - 10 minutes)
- [ ] **Fix starting cash bug** - Change `10000.00` to `1000000` in 2 files
- [ ] **Fix user scoping bugs** - Add `user_id` filters to alerts/reputation/strikes

### Phase 1: Quick Wins (1-2 weeks)
- [ ] Add demand forecasting to inventory pages
- [ ] Implement stockout consequences (lost sales)
- [ ] Add Day 1 time pressure messaging
- [ ] Add consumption visibility (daily sold units notification)

### Phase 2: Core Engagement (2-4 weeks)
- [ ] Implement tutorial quest system
- [ ] Add active spike resolution actions
- [ ] Unlock new locations at levels
- [ ] Unlock new vendors via reputation

### Phase 3: Depth & Strategy (4-8 weeks)
- [ ] Make analytics actionable with AI recommendations
- [ ] Add what-if scenario calculator
- [ ] Implement multi-day planning tools (forecasts, projections)
- [ ] Add bulk order scheduling

### Phase 4: Replayability & Polish (8-12 weeks)
- [ ] Implement prestige system
- [ ] Add dynamic quest generation
- [ ] Create weekly challenges and leaderboards
- [ ] Add endgame scenarios and modes

---

## Conclusion

Moonshine Coffee Management Sim has excellent technical infrastructure with a robust simulation engine, event system, and multi-hop logistics. However, **critical bugs prevent the game from being playable**, and the current gameplay lacks player motivation, visible stakes, and meaningful progression.

### Immediate Action Required

**BEFORE any feature development**, fix these critical bugs (10 minutes of work):

1. ⚠️ **Starting cash bug**: Players start with $100 instead of $10,000 (100x too little)
   - Impact: Game is unplayable - orders cost $100-500, so players go broke immediately
   - Fix: Change `10000.00` → `1000000` in 2 files

2. ⚠️ **User scoping bugs**: Multiplayer data leakage in alerts, reputation, and strikes
   - Impact: Players see each other's data and affect each other's scores
   - Fix: Add `->where('user_id', $user->id)` to 4 queries

**Estimated fix time**: 10 minutes
**Testing time**: 5 minutes
**Total**: 15 minutes to make game playable

### Long-Term Vision

After critical fixes, the proposed improvements focus on:
1. **Making the invisible visible** (demand, forecasts, consequences)
2. **Adding meaningful stakes** (lost sales, reputation impact, bankruptcy risk)
3. **Providing player agency** (spike resolution, strategic planning, vendor selection)
4. **Creating progression** (unlockables, prestige, challenges)

Implementing these changes will transform the game from a passive simulation into an engaging strategic management experience with both short-term tactical decisions and long-term strategic goals.

### Priority Order

1. **Phase 0** (CRITICAL - 15 min): Fix bugs that break core gameplay
2. **Phase 1** (1-2 weeks): Make consumption visible, add stakes
3. **Phase 2** (2-4 weeks): Add progression and player agency
4. **Phase 3+** (4-12 weeks): Strategic depth and replayability

---

**Document Version:** 1.0
**Last Updated:** 2026-01-24
**Author:** Analysis Session
**Related Documents:**
- `docs/gameplay-loop-mechanics-analysis.md`
- `docs/technical-design-document.md`
- `docs/notification-system.md`
