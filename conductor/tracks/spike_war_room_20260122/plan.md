# Implementation Plan - Spike Events War Room & Simulation

## Phase 1: Database & Backend Foundations
- [ ] Task: Update SpikeEvent Migration & Model
    - [ ] Create migration to add `acknowledged_at`, `mitigated_at`, `resolved_at`, `resolved_by`, `resolution_cost`, `action_log` to `spike_events`.
    - [ ] Update `SpikeEvent` model with new fillables and casts (JSON for `action_log`).
    - [ ] Add helper methods/scopes (e.g., `scopeActive`, `scopeResolvedByPlayer`).
- [ ] Task: Fix Delay Spike Logic
    - [ ] Update `DelaySpike::apply()` to store `meta.original_delivery_dates_by_order_id`.
    - [ ] Update `DelaySpike::rollback()` to restore delivery dates from `meta`.
    - [ ] Create unit test `DelaySpikeTest` to verify apply/rollback integrity.
- [ ] Task: Implement Resolution Service Logic
    - [ ] Create `SpikeResolutionService` (or similar).
    - [ ] Implement `resolveEarly(SpikeEvent $spike)` method:
        -   Validate eligibility (`breakdown`, `blizzard` only).
        -   Calculate cost.
        -   Deduct cash (via `TransactionService` or similar).
        -   Trigger `rollback()`.
        -   Update timestamp fields.
    - [ ] Implement `mitigate(SpikeEvent $spike)` method.
- [ ] Task: Conductor - User Manual Verification 'Phase 1' (Protocol in workflow.md)

## Phase 2: Simulation Mechanics (Demand & Price)
- [ ] Task: Implement Demand Simulation
    - [ ] Create `DemandSimulationService`.
    - [ ] Implement `processDailyConsumption()`:
        -   Iterate stores/products.
        -   Calculate baseline + variance.
        -   Check `SpikeEvent` for `demand` type matches.
        -   Apply magnitude multiplier.
        -   Decrement inventory & record stats.
    - [ ] Wire into `SimulationService` loop (post-physics, pre-analysis).
    - [ ] Create `DemandSimulationTest` to verify multiplier effects.
- [ ] Task: Implement Price Simulation
    - [ ] Create `PricingService` (or method in `OrderService`).
    - [ ] Implement `calculateUnitCost(Product $product, Location $vendor)`:
        -   Check `SpikeEvent` for `price` type matches.
        -   Apply magnitude multiplier.
    - [ ] Update `OrderService::createOrder` (or equivalent) to use this logic.
    - [ ] Create `PriceSimulationTest` to verify order cost increases.
- [ ] Task: Conductor - User Manual Verification 'Phase 2' (Protocol in workflow.md)

## Phase 3: Frontend Integration (Dashboard & War Room)
- [ ] Task: Expose Spike Data via Inertia
    - [ ] Update `GameController` (Dashboard & SpikeHistory methods) to return enriched spike data (active spikes, playbooks, history).
    - [ ] Ensure `game.currentSpike` (or list) is passed globally via `HandleInertiaRequests` middleware or per-page props.
- [ ] Task: Clean Up Mock Data
    - [ ] Delete `resources/js/services/spikeService.ts`.
    - [ ] Remove mock data calls from `SpikeMonitor.tsx` and `SpikeHistory.tsx`.
- [ ] Task: Rebuild Dashboard Widget
    - [ ] Refactor `SpikeMonitor.tsx` to use Inertia props (`active_spikes`).
    - [ ] Ensure it displays correct status/timers from backend data.
- [ ] Task: Build War Room UI
    - [ ] Refactor `resources/js/pages/game/spike-history.tsx`.
    - [ ] Implement "Active Events" card grid with Playbook actions.
    - [ ] Implement "History" table with new resolution status columns.
    - [ ] Connect "Resolve Early" button to backend endpoint.
- [ ] Task: Conductor - User Manual Verification 'Phase 3' (Protocol in workflow.md)

## Phase 4: Final Verification & Polish
- [ ] Task: Integration Testing
    - [ ] Create `Feature/SpikeWarRoomTest.php`:
        -   Verify resolving a breakdown restores storage.
        -   Verify resolving a blizzard opens the route.
        -   Verify cash deduction.
    - [ ] Create `Feature/SpikeSimulationTest.php`:
        -   Run simulation days with active demand/price spikes.
        -   Assert inventory drops faster or order costs are higher.
- [ ] Task: UI Polish
    - [ ] Add visual feedback for "Resolved" state (animations/toasts).
    - [ ] Ensure mobile responsiveness for new War Room cards.
- [ ] Task: Conductor - User Manual Verification 'Phase 4' (Protocol in workflow.md)
