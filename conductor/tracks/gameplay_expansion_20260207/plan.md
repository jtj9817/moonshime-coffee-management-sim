# Implementation Plan: Gameplay Loop Expansion (Phases 1-3)

This plan implements the features described in the specification to deepen the gameplay simulation through visibility, consequences, progression, and planning tools.
All tasks in this track must preserve completed Phase 0 invariants (money in cents + strict user isolation).

## Phase 1: Visibility & Consequences (The "Quick Wins")

### Task 1: Stockout & Lost Sales Tracking
- [ ] Task: Create migration and model for `LostSales`.
    - [ ] Write migration for `lost_sales` table (user_id, location_id, product_id, day, quantity_lost, potential_revenue_lost).
    - [ ] Create `LostSales` model with proper casts and relationships.
- [ ] Task: Implement stockout detection and persistence.
    - [ ] Write test for `DemandSimulationService` to verify `LostSales` are recorded on stockout.
    - [ ] Update `DemandSimulationService` to calculate and save missed demand.
    - [ ] Dispatch `StockoutOccurred` event with metadata.

### Task 2: Financial Granularity (P&L per Location)
- [ ] Task: Create migration and model for `LocationDailyMetrics`.
    - [ ] Write migration for `location_daily_metrics` table (revenue, cogs, opex, net_profit, units_sold, stockouts, satisfaction).
    - [ ] Create `LocationDailyMetrics` model.
- [ ] Task: Implement P&L calculation logic.
    - [ ] Write test for `SimulationService` (or new `MetricService`) to verify P&L calculation accuracy (Revenue - COGS - OpEx).
    - [ ] Update `SimulationService` to calculate and store metrics for every active location during the Analysis Tick.

### Task 3: Demand Forecasting Engine
- [ ] Task: Implement `DemandForecastService`.
    - [ ] Write test for `getForecast` to verify projections include base consumption, active spikes, and incoming orders/transfers.
    - [ ] Verify each projection row includes `day_offset`, `predicted_demand`, `predicted_stock`, and `risk_level` (`low|medium|stockout`).
    - [ ] Implement `getForecast` logic to return a 7-day projection array.
- [ ] Task: Create `DemandForecastChart` frontend component.
    - [ ] Create `DemandForecastChart.tsx` using Recharts to display projected stock, stockout reference line, and incoming-delivery bars.
    - [ ] Integrate chart into the Inventory/SKU detail page.

### Task 4: Daily Summary Notifications
- [ ] Task: Implement daily summary aggregation.
    - [ ] Write test for `advanceDay` listener to verify "Daily Summary" Alert creation.
    - [ ] Update simulation logic to aggregate units sold, lost sales, and storage-fee deductions into a summary alert.
- [ ] Task: Dashboard UI for Daily Summary.
    - [ ] Implement a prominent summary display on the Dashboard for the latest "summary" type alert.

### Task 5: Pricing Strategy & Price Elasticity
- [ ] Task: Add `sell_price` to locations.
    - [ ] Create migration to add `sell_price` (integer cents) to `locations`.
- [ ] Task: Implement price elasticity in demand simulation.
    - [ ] Write test for `DemandSimulationService` to verify demand changes based on `sell_price`.
    - [ ] Update demand formula to use: `EffectiveDemand = BaseDemand * (StandardPrice / CurrentPrice)^ElasticityFactor`.

- [ ] Task: Conductor - User Manual Verification 'Phase 1: Visibility & Consequences' (Protocol in workflow.md)

## Phase 2: Core Engagement & Progression

### Task 1: Quest System Architecture
- [ ] Task: Create migrations and models for Quests.
    - [ ] Write migrations for `quests` and `user_quests` tables.
    - [ ] Create `Quest` and `UserQuest` models.
- [ ] Task: Implement `QuestService` and Trigger Architecture.
    - [ ] Write test for `QuestService@checkTriggers` with a mock trigger.
    - [ ] Implement `QuestService` to load active user quests and execute `trigger_class` implementations.
    - [ ] Wire trigger checks to gameplay events (for example `OrderPlaced`, `DayAdvanced`).
- [ ] Task: Frontend Quest Dashboard.
    - [ ] Create `QuestList` component and integrate it into a new "Quests" page or sidebar.

### Task 2: Active Spike Resolution
- [ ] Task: Create migration and model for `SpikeResolution`.
    - [ ] Write migration for `spike_resolutions` table.
- [ ] Task: Implement Spike Resolution logic.
    - [ ] Write test for `resolveSpike` action to verify effect application (duration reduction / demand reduction).
    - [ ] Implement `POST /game/spikes/{spike}/resolve` endpoint and logic.
- [ ] Task: Spike Interaction UI.
    - [ ] Add "Resolve" buttons and cost indicators to Spike alerts or Spike War Room UI.

- [ ] Task: Conductor - User Manual Verification 'Phase 2: Core Engagement & Progression' (Protocol in workflow.md)

## Phase 3: Strategic Planning Tools

### Task 1: "What-If" Scenario Calculator
- [ ] Task: Implement Scenario Calculator frontend logic (initial scope).
    - [ ] Implement reusable calculator logic shared with Demand Forecast formulas (frontend utility or shared computation module).
    - [ ] Add tests for calculator outputs: time-to-stockout and reorder recommendation.
- [ ] Task: Scenario Calculator UI components.
    - [ ] Create `ScenarioPlanner.tsx` standalone tool (frontend-only initial implementation).
    - [ ] Integrate "mini-calc" into Ordering and Transfer dialogs.
    - [ ] Only add a backend/API endpoint if async constraints require it.

### Task 2: Bulk Order Scheduler
- [ ] Task: Create migration and model for `ScheduledOrder`.
    - [ ] Write migration for `scheduled_orders` table with `interval_days` or `cron_expression` plus auto-submit controls.
- [ ] Task: Implement Order Scheduler logic.
    - [ ] Write test for `ScheduledOrderService` to verify automatic order creation on `DayAdvanced`.
    - [ ] Implement logic to process active schedules and create orders.
    - [ ] Implement guarded auto-submit behavior (requires sufficient funds/capacity).
- [ ] Task: Order Scheduler UI.
    - [ ] Add "Schedule this order" option to the Ordering flow.
    - [ ] Create a "Scheduled Orders" management interface.

- [ ] Task: Conductor - User Manual Verification 'Phase 3: Strategic Planning Tools' (Protocol in workflow.md)
