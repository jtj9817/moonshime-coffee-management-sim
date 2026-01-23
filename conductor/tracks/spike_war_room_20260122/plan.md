# Implementation Plan - Spike Events War Room & Simulation

## Phase 1: Database & Backend Foundations
- [x] Task: Update SpikeEvent Migration & Model
    - [x] Create migration to add `acknowledged_at`, `mitigated_at`, `resolved_at`, `resolved_by`, `resolution_cost`, `action_log` to `spike_events`.
    - [x] Update `SpikeEvent` model with new fillables and casts (JSON for `action_log`).
    - [x] Add helper methods/scopes (e.g., `scopeActive`, `scopeResolvedByPlayer`).
    - [x] Update `SimulationService::processEventTick()` to set `resolved_by='time'` + `resolved_at=now()` when a spike expires naturally (not just `is_active=false`).
- [x] Task: Fix Spike Scoping (Multi-user Correctness)
    - [x] Scope `game.currentSpike` to the authenticated user in `HandleInertiaRequests` (avoid `SpikeEvent::where('is_active', true)->first()`).
    - [x] Scope `active_spikes_count` (and any dashboard spike aggregates) to the authenticated user.
    - [x] Ensure all spike queries used for gameplay effects are scoped to the spike owner (`user_id`) to avoid cross-user leakage.
- [x] Task: Fix Delay Spike Logic
    - [x] Update `DelaySpike::apply()` to:
        -   Query only the spike owner's orders (`user_id`) and use state-machine statuses (e.g., `whereState('status', Pending::class/Shipped::class)`).
        -   Store both `delivery_day` (authoritative for delivery processing) and `delivery_date` (display) in `spike.meta` for each affected order.
        -   Update both `delivery_day` and `delivery_date` for affected orders.
    - [x] Update `DelaySpike::rollback()` to restore `delivery_day`/`delivery_date` from `spike.meta` (and only for orders actually modified by this spike).
    - [x] Create unit/feature test(s) to verify apply/rollback integrity and prevent permanent schedule drift.
- [x] Task: Implement Resolution Service Logic
    - [x] Create `SpikeResolutionService` (or similar).
    - [x] Implement `resolveEarly(SpikeEvent $spike)` method:
        -   Validate eligibility (`breakdown`, `blizzard` only).
        -   Calculate cost.
        -   Deduct cash via `GameState` inside a DB transaction (no `TransactionService` exists today).
        -   Trigger rollback (prefer dispatching `SpikeEnded` to reuse existing listener chain).
        -   Prevent re-activation by setting `ends_at_day` to the current day and `is_active=false`.
        -   Update tracking fields (`resolved_by='player'`, `resolved_at`, `resolution_cost`, etc.).
    - [x] Implement `mitigate(SpikeEvent $spike)` method.
- [ ] Task: Conductor - User Manual Verification 'Phase 1' (Protocol in workflow.md)

## Phase 2: Simulation Mechanics (Demand & Price)
- [x] Task: Implement Demand Simulation
    - [x] Create `DemandSimulationService`.
    - [x] Implement `processDailyConsumption()`:
        -   Iterate stores/products.
        -   Calculate baseline + variance.
        -   Check for active `demand` spikes matching the store (and optionally product).
        -   Apply magnitude multiplier.
        -   Decrement inventory & record stats.
    - [ ] Update spike generation rules so `demand` spikes have unambiguous targets (recommended: always set `location_id` to a store; optionally set `product_id` to a specific product).
    - [x] Wire into `SimulationService` loop (post-physics, pre-analysis).
    - [ ] Create `DemandSimulationTest` to verify multiplier effects.
- [x] Task: Implement Price Simulation
    - [x] Create `PricingService` (or method in `OrderService`).
    - [x] Implement `calculateUnitCost(Product $product, array $context = [])` (or similar):
        -   Check for active `price` spikes matching `product_id` (optionally vendor-scoped via `spike.meta.vendor_id` if implemented).
        -   Apply magnitude multiplier.
    - [ ] Update `OrderService::createOrder` to apply pricing multipliers at order placement time (affects `order.total_cost` and thus `DeductCash`).
    - [ ] Update spike generation rules so `price` spikes have unambiguous targets (recommended: always set `product_id`).
    - [ ] Create `PriceSimulationTest` to verify order cost increases.
- [ ] Task: Conductor - User Manual Verification 'Phase 2' (Protocol in workflow.md)

## Phase 3: Frontend Integration (Dashboard & War Room)
- [x] Task: Expose Spike Data via Inertia
    - [x] Update `GameController` (Dashboard & SpikeHistory methods) to return enriched spike data (active spikes, playbooks, history).
    - [x] Pass a user-scoped list of active spikes (`activeSpikes`) via shared props or per-page props (avoid relying on a single global `currentSpike`).
- [x] Task: Clean Up Mock Data
    - [x] Delete `resources/js/services/spikeService.ts`.
    - [x] Delete legacy/disconnected mock UI: `resources/js/components/SpikeMonitor.tsx` and `resources/js/components/SpikeHistory.tsx` (and any remaining references).
- [x] Task: Rebuild Dashboard Widget
    - [x] Implement/Refactor the dashboard spike widget in `resources/js/pages/game/dashboard.tsx` (or extract to `resources/js/components/game/*`) using Inertia props (`activeSpikes`).
    - [x] Ensure it uses turn-based semantics (no polling) and updates on Inertia reload after day advance / resolve actions.
- [x] Task: Build War Room UI
    - [x] Refactor `resources/js/pages/game/spike-history.tsx`.
    - [x] Implement "Active Events" card grid with Playbook actions.
    - [x] Implement "History" table with new resolution status columns.
    - [x] Connect "Resolve Early" button to backend endpoint.
- [x] Task: Conductor - User Manual Verification 'Phase 3' (Protocol in workflow.md)

## Phase 4: Final Verification & Polish
- [x] Task: Integration Testing
    - [x] Create `Feature/SpikeWarRoomTest.php`:
        -   Verify resolving a breakdown restores storage.
        -   Verify resolving a blizzard opens the route.
        -   Verify cash deduction.
    - [x] Create `Feature/SpikeSimulationTest.php`:
        -   Run simulation days with active demand/price spikes.
        -   Assert inventory drops faster or order costs are higher.
- [x] Task: UI Polish
    - [x] Add visual feedback for "Resolved" state (animations/toasts).
    - [x] Ensure mobile responsiveness for new War Room cards.
- [ ] Task: Conductor - User Manual Verification 'Phase 4' (Protocol in workflow.md)
