# Specification: Track - Gameplay Loop Expansion (Phases 1-3)

## 1. Overview
This track implements the core gameplay loop expansion as outlined in Phases 1, 2, and 3 of the `gameplay-features-implementation-spec.md`. The goal is to transform the simulation from a basic inventory manager into a deep, strategic management game with visibility into simulation mechanics, consequences for failure, progression systems, and advanced planning tools.

## 2. Functional Requirements

### Phase 1: Visibility & Consequences
- **Demand Forecasting Engine:** Implement `DemandForecastService` to project stock levels and stockout risks. Create a `DemandForecastChart` component.
- **Stockout & Lost Sales Tracking:** Track economic damage when demand exceeds inventory via a new `lost_sales` table and event-driven logic.
- **Daily Summary Notifications:** Generate an aggregate report after each `advanceDay` showing sales, losses, and costs.
- **Financial Granularity (P&L):** Implement `location_daily_metrics` to track revenue, COGS, OpEx, and Net Profit per location.
- **Pricing Strategy:** Add `sell_price` to locations and implement price elasticity in the demand simulation.

### Phase 2: Core Engagement & Progression
- **Quest System Architecture:** Implement a state-driven quest system (`quests` and `user_quests` tables) with Transactional, Financial, Resilience, and Operational triggers.
- **Active Spike Resolution:** Allow players to spend Cash or Reputation to mitigate active Spike events (e.g., Expedite or Marketing).

### Phase 3: Strategic Planning Tools
- **"What-If" Scenario Calculator:** Create a planning tool that calculates time-to-stockout and recommended order quantities. This will be available as both a standalone page and an integrated helper within ordering dialogs.
- **Bulk Order Scheduler:** Implement a system to schedule repeating orders with "Auto-Submit" functionality (dependent on funds and capacity).

## 3. Technical Constraints & Standards
- **Monetary Integrity:** All calculations and persistence MUST use integer cents.
- **User Isolation:** All data, metrics, and quests MUST be strictly scoped to the authenticated user.
- **Architecture:** Business logic resides in Services; Controllers remain thin.
- **State Management:** Use `spatie/laravel-model-states` for complex transitions (Quests/Orders) where applicable.

## 4. Acceptance Criteria
- [ ] Players can see a 7-day stock projection for any SKU.
- [ ] Stockouts result in persistent `LostSales` records and a reduction in potential revenue.
- [ ] A "Daily Summary" modal or alert appears after advancing the day.
- [ ] Players can view a P&L statement for each location.
- [ ] Adjusting prices dynamically affects demand based on elasticity.
- [ ] Quests trigger and reward correctly across all four categories (Transactional, Financial, Resilience, Operational).
- [ ] Players can "Resolve" a spike to reduce its duration or intensity.
- [ ] The Scenario Calculator provides accurate "Safe-to-date" projections.
- [ ] Scheduled orders execute automatically on `advanceDay` if valid.
