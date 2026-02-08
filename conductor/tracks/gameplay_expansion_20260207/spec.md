# Specification: Track - Gameplay Loop Expansion (Phases 1-3)

## 1. Overview
This track implements Phases 1, 2, and 3 from `docs/gameplay-features-implementation-spec.md` to deepen gameplay through visibility, consequences, progression, and planning tools.

Phase 0 is already complete and is a hard prerequisite: all new work must preserve money-in-cents invariants and strict per-user data isolation.

## 2. Functional Requirements

### Phase 1: Visibility & Consequences
- **Demand Forecasting Engine:** Implement `DemandForecastService::getForecast()` for a 7-day projection using base consumption, active spikes, and incoming Orders/Transfers. Return daily `day_offset`, `predicted_demand`, `predicted_stock`, and `risk_level` (`low|medium|stockout`).
- **Demand Forecast Chart:** Implement `DemandForecastChart.tsx` with projected stock line, zero stockout reference line, and incoming-delivery bars.
- **Stockout & Lost Sales Tracking:** Persist `lost_sales` when demand exceeds inventory, calculate `quantity_lost` and `potential_revenue_lost` (cents), and dispatch `StockoutOccurred`.
- **Daily Summary Notifications:** On `advanceDay`, aggregate units sold, lost sales, and storage-fee cash deducted, then create a `summary`/`info` alert with structured metadata for dashboard display.
- **Financial Granularity (P&L):** Implement `location_daily_metrics` with revenue, COGS, OpEx, net profit, units sold, stockouts, and satisfaction per location/day. Use OpEx formula `rent + (inventory quantity * storage_cost)`.
- **Pricing Strategy:** Add `sell_price` (integer cents) and apply elasticity formula `EffectiveDemand = BaseDemand * (StandardPrice / CurrentPrice)^ElasticityFactor`.

### Phase 2: Core Engagement & Progression
- **Quest System Architecture:** Implement `quests` and `user_quests` using trigger classes (`trigger_class`, `trigger_params`) and rewards metadata. `QuestService::checkTriggers()` runs on gameplay events (for example, `OrderPlaced`, `DayAdvanced`) and marks quest completion.
- **Active Spike Resolution:** Implement `spike_resolutions` and `POST /game/spikes/{spike}/resolve` for actions like expedite/marketing, with resource validation and effect persistence.

### Phase 3: Strategic Planning Tools
- **"What-If" Scenario Calculator (Initial Scope):** Frontend-only implementation (`ScenarioPlanner.tsx`) with stockout horizon and reorder recommendation outputs. Reuse demand-forecast math via shared frontend logic; use an API endpoint only if an async constraint requires it.
- **Bulk Order Scheduler:** Implement `scheduled_orders` with `interval_days` or `cron_expression`, execute schedules on `DayAdvanced`, and support optional auto-submit when constraints (funds/capacity) are satisfied.

## 3. Technical Constraints & Standards
- **Monetary Integrity:** All persistence and domain math use integer cents; display conversion happens at serialization/render boundaries only.
- **User Isolation:** All gameplay reads/writes are strictly user-scoped.
- **Architecture:** Controllers remain thin; business logic lives in Services.
- **Inertia-First Delivery:** Use Inertia pages/props for gameplay flows; avoid pure API endpoints unless truly necessary.
- **Testing:** Every new Service method gets unit coverage; every controller action gets feature coverage.

## 4. Acceptance Criteria
- [ ] Players can view accurate 7-day stock projections (including inbound deliveries) for a selected SKU/location.
- [ ] Stockouts create persistent `lost_sales` records with correct lost quantity and potential revenue values.
- [ ] Advancing the day produces a daily summary alert with units sold, lost sales, and storage-fee deductions.
- [ ] Players can view per-location daily P&L and operational metrics.
- [ ] Changing `sell_price` affects demand according to the elasticity rule.
- [ ] Quest triggers execute from gameplay events and grant configured rewards on completion.
- [ ] Players can resolve active spikes, paying required costs to reduce duration/intensity.
- [ ] Scenario Planner (frontend) returns accurate stockout horizon and reorder guidance.
- [ ] Scheduled orders run on `advanceDay` and auto-submit only when valid.
