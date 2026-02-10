# Implementation Plan: Gameplay Loop Expansion (Phases 1-3)

This plan implements the features described in the specification to deepen the gameplay simulation through visibility, consequences, progression, and planning tools.
All tasks in this track must preserve completed Phase 0 invariants (money in cents + strict user isolation).

## Phase 1: Visibility & Consequences (The "Quick Wins") [checkpoint: 69b2c01]

### Task 1: Stockout & Lost Sales Tracking
- [x] Task: Create migration and model for `LostSales`.
    - [x] Write migration for `lost_sales` table (user_id, location_id, product_id, day, quantity_lost, potential_revenue_lost).
    - [x] Create `LostSales` model with proper casts and relationships.
- [x] Task: Implement stockout detection and persistence.
    - [x] Write test for `DemandSimulationService` to verify `LostSales` are recorded on stockout.
    - [x] Update `DemandSimulationService` to calculate and save missed demand.
    - [x] Dispatch `StockoutOccurred` event with metadata.

### Task 2: Financial Granularity (P&L per Location)
- [x] Task: Create migration and model for `LocationDailyMetrics`.
    - [x] Write migration for `location_daily_metrics` table (revenue, cogs, opex, net_profit, units_sold, stockouts, satisfaction).
    - [x] Create `LocationDailyMetrics` model.
- [x] Task: Implement P&L calculation logic.
    - [x] Write test for `SimulationService` (or new `MetricService`) to verify P&L calculation accuracy (Revenue - COGS - OpEx).
    - [x] Update `SimulationService` to calculate and store metrics for every active location during the Analysis Tick.

### Task 3: Demand Forecasting Engine
- [x] Task: Implement `DemandForecastService`.
    - [x] Write test for `getForecast` to verify projections include base consumption, active spikes, and incoming orders/transfers.
    - [x] Verify each projection row includes `day_offset`, `predicted_demand`, `predicted_stock`, and `risk_level` (`low|medium|stockout`).
    - [x] Implement `getForecast` logic to return a 7-day projection array.
- [x] Task: Create `DemandForecastChart` frontend component.
    - [x] Create `DemandForecastChart.tsx` using Recharts to display projected stock, stockout reference line, and incoming-delivery bars.
    - [x] Integrate chart into the Inventory/SKU detail page.

### Task 4: Daily Summary Notifications
- [x] Task: Implement daily summary aggregation.
    - [x] Write test for `advanceDay` listener to verify "Daily Summary" Alert creation.
    - [x] Update simulation logic to aggregate units sold, lost sales, and storage-fee deductions into a summary alert.
- [x] Task: Dashboard UI for Daily Summary.
    - [x] Implement a prominent summary display on the Dashboard for the latest "summary" type alert.

### Task 5: Pricing Strategy & Price Elasticity
- [x] Task: Add `sell_price` to locations.
    - [x] Create migration to add `sell_price` (integer cents) to `locations`.
- [x] Task: Implement price elasticity in demand simulation.
    - [x] Write test for `DemandSimulationService` to verify demand changes based on `sell_price`.
    - [x] Update demand formula to use: `EffectiveDemand = BaseDemand * (StandardPrice / CurrentPrice)^ElasticityFactor`.

- [ ] Task: Conductor - User Manual Verification 'Phase 1: Visibility & Consequences' (Protocol in workflow.md)

## Phase 2: Core Engagement & Progression

### Task 1: Quest System Architecture
- [x] Task: Create migrations and models for Quests.
    - [x] Write migrations for `quests` and `user_quests` tables.
    - [x] Create `Quest` and `UserQuest` models.
- [x] Task: Implement `QuestService` and Trigger Architecture.
    - [x] Write test for `QuestService@checkTriggers` with a mock trigger.
    - [x] Implement `QuestService` to load active user quests and execute `trigger_class` implementations.
    - [x] Wire trigger checks to gameplay events (for example `OrderPlaced`, `DayAdvanced`).
- [x] Task: Frontend Quest Dashboard.
    - [x] Create `QuestList` component and integrate it into a new "Quests" page or sidebar.

### Task 2: Active Spike Resolution
- [x] Task: Create migration and model for `SpikeResolution`.
    - [x] Write migration for `spike_resolutions` table.
- [x] Task: Implement Spike Resolution logic.
    - [x] Write test for `resolveSpike` action to verify effect application (duration reduction / demand reduction).
    - [x] Implement `POST /game/spikes/{spike}/resolve` endpoint and logic.
- [x] Task: Spike Interaction UI.
    - [x] Add "Resolve" buttons and cost indicators to Spike alerts or Spike War Room UI.

- [x] Task: Conductor - User Manual Verification 'Phase 2: Core Engagement & Progression' (Protocol in workflow.md)
    - Checkpoint SHA: 83a1f01

## Phase 3: Strategic Planning Tools

### Task 1: "What-If" Scenario Calculator
- [x] Task: Implement Scenario Calculator frontend logic (initial scope).
    - [x] Implement reusable calculator logic shared with Demand Forecast formulas (frontend utility or shared computation module).
    - [x] Add tests for calculator outputs: time-to-stockout and reorder recommendation.
- [x] Task: Scenario Calculator UI components.
    - [x] Create `ScenarioPlanner.tsx` standalone tool (frontend-only initial implementation).
    - [x] Integrate "mini-calc" into Ordering and Transfer dialogs.
    - [x] Only add a backend/API endpoint if async constraints require it.

### Task 2: Bulk Order Scheduler
- [x] Task: Create migration and model for `ScheduledOrder`.
    - [x] Write migration for `scheduled_orders` table with `interval_days` or `cron_expression` plus auto-submit controls.
- [x] Task: Implement Order Scheduler logic.
    - [x] Write test for `ScheduledOrderService` to verify automatic order creation on `DayAdvanced`.
    - [x] Implement logic to process active schedules and create orders.
    - [x] Implement guarded auto-submit behavior (requires sufficient funds/capacity).
- [x] Task: Order Scheduler UI.
    - [x] Add "Schedule this order" option to the Ordering flow.
    - [x] Create a "Scheduled Orders" management interface.

### Task 3: Strict Location Ownership Enforcement
- [x] Task: Introduce explicit location ownership mapping.
    - [x] Create migration/model for `user_locations` (`user_id`, `location_id`, unique composite).
    - [x] Backfill ownership from existing user inventory and vendor access.
- [x] Task: Enforce ownership in ordering/scheduling flows.
    - [x] Validate destination/source locations against authenticated user's owned locations.
    - [x] Remove inventory-proxy ownership checks for scheduled orders.
- [x] Task: Scope shared game location props by ownership.
    - [x] Return only owned locations from Inertia shared props to keep UI aligned with backend validation.
- [x] Task: Verification updates.
    - [x] Add/adjust tests and manual verification scripts for ownership-aware scheduled order creation.

- [ ] Task: Conductor - User Manual Verification 'Phase 3: Strategic Planning Tools' (Protocol in workflow.md)
