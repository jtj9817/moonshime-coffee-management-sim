# Specification: Spike Events War Room, Resolution UX & Simulation Mechanics

## 1. Overview
This feature implements a comprehensive "War Room" UX for Spike Events and activates the simulation mechanics for `demand` and `price` spikes, which were previously placeholders. It transforms the `/game/spike-history` page into an interactive command center with "Playbooks" and "Resolve Early" actions. It also unifies the frontend by replacing disconnected mock components with real data-driven widgets and integrates consumption and dynamic pricing logic into the backend simulation loop.

## 2. Functional Requirements

### 2.1 Data Model Enhancements
-   **New Tracking Columns:** Add `acknowledged_at` (timestamp), `mitigated_at` (timestamp), `resolved_at` (timestamp), and `resolved_by` (enum/string: 'time', 'player') to the `spike_events` table.
-   **Resolution Cost:** Add `resolution_cost` (decimal/integer) to store the cash amount paid for early resolution.
-   **Action Log:** Add `action_log` (JSON) to track player interactions (e.g., "Viewed Playbook", "Clicked Transfer Link").
-   **Resolved Semantics:** Natural expiry must set `resolved_by='time'` and `resolved_at`, while early resolution must set `resolved_by='player'` and `resolved_at`.
-   **Delay Spike Fix:** Update `DelaySpike` to be user-scoped and state-machine aware, and to store/restore both `delivery_day` (authoritative for delivery processing) and `delivery_date` (display). Persist originals in `spike.meta` per affected order so rollback works on natural expiry or early resolution.
-   **Baseline Data:** Ensure baseline demand data exists (in config or DB) for consumption simulation.

### 2.2 Backend Logic: Simulation Mechanics
-   **Demand Simulation:**
    -   Create `DemandSimulationService` to run daily consumption logic.
    -   Integrate into `SimulationService` (e.g., `processConsumptionTick()`) after physics/deliveries but before analysis.
    -   Logic:
        1.  Compute baseline daily usage per product/store.
        2.  Apply random variance.
        3.  **Apply Multiplier:** If a `demand` spike is active for the store/product, multiply usage by `spike.magnitude`.
        4.  Decrement inventory.
        5.  Record stockouts (and potentially trigger alerts).
    -   **Targeting Constraint (Recommended):** Ensure `demand` spikes have unambiguous targets. Prefer requiring `location_id` to be a store location; optionally include `product_id` for product-specific demand surges.
-   **Price Simulation:**
    -   Create `PricingService` (or enhance `OrderService`) to calculate effective unit prices.
    -   Logic: When placing an order, check for active `price` spikes matching the product (and vendor if scoped).
    -   **Apply Multiplier:** Adjust effective unit cost by `spike.magnitude` for the order.
    -   Ensure `DeductCash` uses this adjusted total.
    -   **Targeting Constraint (Recommended):** Ensure `price` spikes have unambiguous targets. Prefer requiring `product_id` to be present; optional vendor scoping can be represented via `spike.meta.vendor_id`.

### 2.3 Backend Logic: Resolution & Mitigation
-   **Early Resolution Endpoint:** Implement `POST /game/spikes/{spike}/resolve` for `breakdown` and `blizzard` types.
    -   Requires validation of cash funds.
    -   Deducts cash.
    -   Triggers immediate rollback (restores storage/route).
    -   Prevents re-activation by setting `ends_at_day` to current day (or immediate closure) and `is_active = false`.
    -   Updates tracking fields (`resolved_by='player'`, `resolved_at`, `resolution_cost`, etc.).
-   **Mitigation Tracking:** Implement `POST /game/spikes/{spike}/mitigate` (or infer from actions) to mark a spike as `mitigated_at`.
-   **Playbook Data:** API should return "Playbook" metadata for each active spike, including:
    -   Description of impact.
    -   Suggested actions (deep links to Ordering, Transfers, etc.).
    -   Resolution options (if available) with costs.
-   **Natural Expiry Tracking:** When spikes expire naturally in `SimulationService`, they must be marked with `resolved_by='time'` and `resolved_at` (not just `is_active=false`).
-   **Authorization:** Resolution/mitigation endpoints must enforce that the spike belongs to the authenticated user (`spike.user_id === auth()->id()`).

### 2.4 Frontend: War Room (`/game/spike-history`)
-   **UI Redesign:** Split the view into two sections:
    -   **Active Events:** Cards displaying current spikes with:
        -   Countdown timer (`ends_at_day`).
        -   Impact details.
        -   "Playbook" action buttons (Deep links).
        -   "Resolve Early" button (for `breakdown`, `blizzard` only) with cost confirmation.
    -   **History:** A table view of past spikes, updated to reflect "Resolved by Player" vs "Expired".
-   **Interactivity:**
    -   Clicking "Resolve" calls the backend endpoint and refreshes the state.
    -   Clicking Playbook links updates the `action_log`.
    -   **Turn-Based UI:** No polling; spike UI updates via Inertia reload after day advance / resolve actions.

### 2.5 Frontend: Dashboard Integration
-   **Remove Mocks:** Delete `resources/js/services/spikeService.ts` and associated mock logic.
-   **Dashboard Widget:** Rebuild the Dashboard spike widget to consume real `SpikeEvent` data passed strictly via **Inertia props** (turn-based, no polling).
    -   The Dashboard controller must inject active spikes into the page props.
    -   The widget will rely on Inertia's state preservation and reload mechanisms; no separate API polling will be used.
-   **Consistency:** Ensure the Dashboard widget shows the same active spikes and status as the War Room.

## 3. Non-Functional Requirements
-   **Data Integrity:** "Delay" spikes must strictly restore original `delivery_day`/`delivery_date` to prevent permanent schedule drift.
-   **Performance:** Spikes query should be efficient, scoped to the current user.
-   **UX:** "Resolve" actions must provide immediate visual feedback (optimistic UI or fast reload).

## 4. Acceptance Criteria
-   **AC1:** `spike_events` table includes all new columns (`acknowledged_at`, `mitigated_at`, `resolved_at`, `resolved_by`, `resolution_cost`, `action_log`).
-   **AC2:** `DelaySpike` correctly restores original order `delivery_day`/`delivery_date` upon rollback.
-   **AC3:** Players can pay to resolve `breakdown` (restores storage) and `blizzard` (restores route) spikes immediately via the War Room.
-   **AC4:** `demand`, `price`, and `delay` spikes show Playbook suggestions but DO NOT offer an "Early Resolve" button.
-   **AC5:** The Dashboard Spike Monitor widget reflects real backend data passed via Inertia props; no mock data remains in the codebase.
-   **AC6:** Active `demand` spikes visibly increase daily inventory consumption for the affected product/location.
-   **AC7:** Active `price` spikes visibly increase the unit cost of new orders for the affected product.
-   **AC8:** Natural spike expiry is recorded as `resolved_by='time'` + `resolved_at` for history reporting (not just `is_active=false`).
-   **AC9:** Spike data shown in shared props and gameplay effects is scoped to the authenticated user.

## 5. Out of Scope
-   Complex "Early Resolution" mechanics for `demand`, `price`, or `delay` (e.g., demand shaping, contract negotiation) - these are strictly Playbook-only for now.
