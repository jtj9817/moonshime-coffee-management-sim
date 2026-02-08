# PHASE-1-REVIEW: Phase 1 (Visibility & Consequences) Code Review

## Summary
A code review of the Phase 1 implementation (Commit `69b2c01`) identified critical performance bottlenecks (N+1 queries) and a logic error in financial calculations (COGS) that must be addressed to ensure system stability and data accuracy.

## Context
- **Commit**: `69b2c016d73efc894c26218a2b24ca92681df238`
- **Features**: Stockout tracking, Location P&L metrics, Demand Forecasting, Daily Summaries.
- **Critical Constraints**: Money in integer cents, efficient simulation for N locations/products.

## Findings

### 🔴 Critical / High Priority

#### TICKET-001: Incorrect COGS Calculation (Weighted vs Simple Average) [RESOLVED]
- **Severity**: High (Data Integrity)
- **Location**: `app/Listeners/CreateLocationDailyMetrics.php:106`
- **Description**: The current implementation uses `avg('order_items.cost_per_unit')` to calculate the Cost of Goods Sold. This calculates a **simple average** of the price per order line, ignoring the *quantity* purchased in each order.
- **Example**:
    - Order A: 1 unit @ $10.00
    - Order B: 1000 units @ $5.00
    - **Current Result**: ($10 + $5) / 2 = $7.50 avg cost.
    - **Correct Result**: (($10 * 1) + ($5 * 1000)) / 1001 ≈ $5.005 avg cost.
- **Recommendation**: Implement weighted average cost calculation: `SUM(cost * quantity) / SUM(quantity)`.
- **Resolution**: Implemented weighted average calculation in `CreateLocationDailyMetrics`.

#### TICKET-002: N+1 Query in COGS Calculation [RESOLVED]
- **Severity**: High (Performance)
- **Location**: `app/Listeners/CreateLocationDailyMetrics.php:95`
- **Description**: The `calculateCogs` method iterates through every product sold at a location and executes a separate database query to fetch historical order costs.
- **Impact**: O(P * L) queries per day, where P is sold products and L is locations.
- **Recommendation**: Pre-fetch cost statistics for all relevant products in a single aggregated query before the loop, keyed by `product_id`.
- **Resolution**: Implemented batch querying for all products sold at a location in `CreateLocationDailyMetrics`.

#### TICKET-003: N+1 Query in Demand Simulation (Spikes) [RESOLVED]
- **Severity**: High (Performance)
- **Location**: `app/Services/DemandSimulationService.php:68`
- **Description**: The `getDemandMultiplier` method is called inside a nested loop (Stores -> Inventories). This causes a separate database query for `SpikeEvent` for **every single inventory item** processed during the daily simulation.
- **Impact**: O(I) queries per day, where I is total inventory records. This will kill performance as the game scales.
- **Recommendation**: Fetch all active demand spikes for the user once at the start of `processDailyConsumption`, then filter them in memory inside the loop.
- **Resolution**: Implemented spike pre-loading and in-memory filtering in `DemandSimulationService`.

### 🟡 Medium Priority

#### TICKET-004: Reliance on `active()` Scope in Simulation [RESOLVED]
- **Severity**: Medium (Robustness)
- **Location**: `app/Services/DemandSimulationService.php:68`
- **Description**: The logic relies on the `active()` scope. If the `is_active` flag on `SpikeEvent` is not perfectly synchronized with the simulation's `$day` (e.g., if updated in a later listener), the simulation might apply expired or future spikes.
- **Recommendation**: Explicitly check `starts_at_day <= $day` and `ends_at_day > $day` within the simulation logic, matching the robustness of `DemandForecastService`.
- **Resolution**: Added explicit day-based filtering to `DemandSimulationService` spike logic.

#### TICKET-005: Duplicated Storage Fee Logic [RESOLVED]
- **Severity**: Medium (Maintainability)
- **Location**: `app/Listeners/CreateDailySummaryAlert.php:50`
- **Description**: The storage fee calculation logic is duplicated here (presumably copied from `ApplyStorageCosts`).
- **Impact**: If the storage cost formula changes (e.g., volume-based pricing), the alert will report incorrect fees unless manually updated.
- **Recommendation**: Extract the calculation into a reusable service method (e.g., `InventoryService::calculateStorageFees($userId)`).
- **Resolution**: Created `StorageFeeCalculator` service and refactored listeners to use it.

## Action Plan
1.  **Refactor `CreateLocationDailyMetrics`**: [DONE]
    -   Implement weighted average COGS.
    -   Batch query order history for all products.
2.  **Refactor `DemandSimulationService`**: [DONE]
    -   Pre-load SpikeEvents.
    -   Add explicit day-based filtering for spikes.
3.  **Refactor `CreateDailySummaryAlert`**: [DONE]
    -   Centralize storage fee calculation if time permits, or add TODO to link it to the source of truth.