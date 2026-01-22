# Implementation Plan - Spike Events War Room & Simulation

## Phase 1: Database & Backend Foundations
- [ ] Task: Update SpikeEvent Migration & Model
    - [ ] Create migration to add `acknowledged_at`, `mitigated_at`, `resolved_at`, `resolved_by`, `resolution_cost`, `action_log` to `spike_events`.
    - [ ] Update `SpikeEvent` model with new fillables and casts (JSON for `action_log`).
    - [ ] Add helper methods/scopes (e.g., `scopeActive`, `scopeResolvedByPlayer`).
    - [ ] Update `SimulationService::processEventTick()` to set `resolved_by='time'` + `resolved_at=now()` when a spike expires naturally (not just `is_active=false`).
- [ ] Task: Fix Spike Scoping (Multi-user Correctness)
    - [ ] Scope `game.currentSpike` to the authenticated user in `HandleInertiaRequests` (avoid `SpikeEvent::where('is_active', true)->first()`).
    - [ ] Scope `active_spikes_count` (and any dashboard spike aggregates) to the authenticated user.
    - [ ] Ensure all spike queries used for gameplay effects are scoped to the spike owner (`user_id`) to avoid cross-user leakage.
- [ ] Task: Fix Delay Spike Logic
    - [ ] Update `DelaySpike::apply()` to:
        -   Query only the spike owner's orders (`user_id`) and use state-machine statuses (e.g., `whereState('status', Pending::class/Shipped::class)`).
        -   Store both `delivery_day` (authoritative for delivery processing) and `delivery_date` (display) in `spike.meta` for each affected order.
        -   Update both `delivery_day` and `delivery_date` for affected orders.
    - [ ] Update `DelaySpike::rollback()` to restore `delivery_day`/`delivery_date` from `spike.meta` (and only for orders actually modified by this spike).
    - [ ] Create unit/feature test(s) to verify apply/rollback integrity and prevent permanent schedule drift.
- [ ] Task: Implement Resolution Service Logic
    - [ ] Create `SpikeResolutionService` (or similar).
    - [ ] Implement `resolveEarly(SpikeEvent $spike)` method:
        -   Validate eligibility (`breakdown`, `blizzard` only).
        -   Calculate cost.
        -   Deduct cash via `GameState` inside a DB transaction (no `TransactionService` exists today).
        -   Trigger rollback (prefer dispatching `SpikeEnded` to reuse existing listener chain).
        -   Prevent re-activation by setting `ends_at_day` to the current day and `is_active=false`.
        -   Update tracking fields (`resolved_by='player'`, `resolved_at`, `resolution_cost`, etc.).
    - [ ] Implement `mitigate(SpikeEvent $spike)` method.
- [ ] Task: Conductor - User Manual Verification 'Phase 1' (Protocol in workflow.md)

## Phase 2: Simulation Mechanics (Demand & Price)
- [ ] Task: Implement Demand Simulation
    - [ ] Create `DemandSimulationService`.
    - [ ] Implement `processDailyConsumption()`:
        -   Iterate stores/products.
        -   Calculate baseline + variance.
        -   Check for active `demand` spikes matching the store (and optionally product).
        -   Apply magnitude multiplier.
        -   Decrement inventory & record stats.
    - [ ] Update spike generation rules so `demand` spikes have unambiguous targets (recommended: always set `location_id` to a store; optionally set `product_id` to a specific product).
    - [ ] Wire into `SimulationService` loop (post-physics, pre-analysis).
    - [ ] Create `DemandSimulationTest` to verify multiplier effects.
- [ ] Task: Implement Price Simulation
    - [ ] Create `PricingService` (or method in `OrderService`).
    - [ ] Implement `calculateUnitCost(Product $product, array $context = [])` (or similar):
        -   Check for active `price` spikes matching `product_id` (optionally vendor-scoped via `spike.meta.vendor_id` if implemented).
        -   Apply magnitude multiplier.
    - [ ] Update `OrderService::createOrder` to apply pricing multipliers at order placement time (affects `order.total_cost` and thus `DeductCash`).
    - [ ] Update spike generation rules so `price` spikes have unambiguous targets (recommended: always set `product_id`).
    - [ ] Create `PriceSimulationTest` to verify order cost increases.
- [ ] Task: Conductor - User Manual Verification 'Phase 2' (Protocol in workflow.md)

## Phase 3: Frontend Integration (Dashboard & War Room)
- [ ] Task: Expose Spike Data via Inertia
    - [ ] Update `GameController` (Dashboard & SpikeHistory methods) to return enriched spike data (active spikes, playbooks, history).
    - [ ] Pass a user-scoped list of active spikes (`activeSpikes`) via shared props or per-page props (avoid relying on a single global `currentSpike`).
- [ ] Task: Clean Up Mock Data
    - [ ] Delete `resources/js/services/spikeService.ts`.
    - [ ] Delete legacy/disconnected mock UI: `resources/js/components/SpikeMonitor.tsx` and `resources/js/components/SpikeHistory.tsx` (and any remaining references).
- [ ] Task: Rebuild Dashboard Widget
    - [ ] Implement/Refactor the dashboard spike widget in `resources/js/pages/game/dashboard.tsx` (or extract to `resources/js/components/game/*`) using Inertia props (`activeSpikes`).
    - [ ] Ensure it uses turn-based semantics (no polling) and updates on Inertia reload after day advance / resolve actions.
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
