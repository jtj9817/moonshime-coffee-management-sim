# Implementation Plan - Logistics Stabilization & Cleanup

## Phase 1: Architectural Cleanup & Standardisation [checkpoint: 41e2538]
- [x] Task: Refactor `GameController::calculateKPIs`
    - [x] Identify and remove redundant "Logistics Health" from the generic KPI array.
    - [x] Update frontend `dashboard.tsx` to use the dedicated top-level `logistics_health` prop and remove manual filtering logic.
- [x] Task: Update pathfinding API with `is_premium` flag
    - [x] Update `LogisticsService` to algorithmically set `is_premium = true` when an alternative route is suggested.
    - [x] Update the `GET /api/logistics/path` endpoint and response DTO.
- [x] Task: Standardise Restock Form UX
    - [x] Update `resources/js/pages/game/transfers.tsx` to implement "Informational Blocking".
    - [x] Ensure blocked options remain visible with status messages but prevent submission.
    - [x] Update `docs/technical-design-document.md` to reflect this standard.
- [x] Task: Conductor - User Manual Verification 'Architectural Cleanup & Standardisation' (Protocol in workflow.md)

## Phase 2: Data Integrity & Persistence [checkpoint: e885170]
- [x] Task: Audit and Update Route Migrations
    - [x] Update `routes` table migration to add strict foreign key constraints for `source_id` and `target_id`.
    - [x] Refactor `weights` JSON to explicit `cost`, `transit_days`, and `capacity` columns.
    - [x] Run migration and verify database-level enforcement.
- [x] Task: Verify SpikeEvent DAG Persistence
    - [x] Audit `SpikeEvent` model and database for `parent_id` and `type` fields.
    - [x] Write a unit test ensuring causal chains (Root -> Symptom) persist correctly.
- [x] Task: Conductor - User Manual Verification 'Data Integrity & Persistence' (Protocol in workflow.md)

## Phase 3: Archive Synchronization & Documentation [checkpoint: 757a7ad]
- [x] Task: Audit Archived Plans
    - [x] Sync `hybrid_event_topology_20260116/plan.md` by marking completed tasks as `[x]`.
    - [x] Ensure all archived task descriptions accurately reflect the current code state.
- [x] Task: Documentation Audit
    - [x] Verify all manual verification scripts in `tests/manual/` are functional and documented.
- [x] Task: Conductor - User Manual Verification 'Archive Synchronization & Documentation' (Protocol in workflow.md)

## Phase 4: Gameplay Loop Verification [checkpoint: d5912b9]
- [x] Task: Implement Gameplay Loop Feature Test
    - [x] Write a test for the full sequence: Initial State (Day 1) -> Decision-making -> Stochastic States (Day 2+).
    - [x] Verify that Day 1 remains stable and deterministic with no random events.
    - [x] Verify full 5-day simulation cycle:
        - [x] Day 2: Spike activation and initial stochastic changes.
        - [x] Day 3-4: Decision persistence and state progression.
        - [x] Day 5: Spike expiration and state restoration/cleanup.
    - [x] Verify that user decisions (Policies/Orders) persist correctly across simulation ticks.
    - [x] Simulate player agency (placing alternative orders during disruptions) and economic impact (cash deduction).
- [~] Task: Implement Inventory & Economic Layer Gameplay Tests
    - [ ] Task: Inventory Changes on Order Delivery (Multi-product, missing records, atomic updates)
    - [ ] Task: Emergency Order Delivery Timing (Route-based transit days, simultaneous deliveries)
    - [ ] Task: Daily Storage Cost Application (Scaling, zero-cost, negative cash handling)
    - [ ] Task: Multiple Product Handling (Complex order/storage cost scenarios)
    - [ ] Task: Order Cancellation and Inventory Rollback (Refunds, blocking delivered cancellations)
    - [ ] Task: Route Capacity and Throughput Limits (Capacity exhaustion, max daily shipments)
- [ ] Task: Conductor - User Manual Verification 'Gameplay Loop Verification' (Protocol in workflow.md)

## Phase 5: Advanced Stress Testing
- [~] Task: Implement "The Cascade" Stress Test (Scenario A)
    - [ ] Trigger a Root Spike and verify 10+ Symptom Alerts are generated across the graph.
    - [ ] Verify automatic system recovery (Route restoration and Alert resolution) upon spike expiration.
- [ ] Task: Implement "The Decision Stressor" Stress Test (Scenario B)
    - [ ] Simulate concurrent Price + Demand + Breakdown spikes forcing pathfinding recalculations.
    - [ ] Verify server-side persistence of "premium" route selection and cost impact.
- [ ] Task: Implement "The Recursive Resolution" Stress Test (Scenario C)
    - [ ] Trigger Root -> Symptom -> Task chain.
    - [ ] Verify Root resolution clears the chain, while Task resolution does not prematurely end the Spike.
- [ ] Task: Conductor - User Manual Verification 'Advanced Stress Testing' (Protocol in workflow.md)

## Phase 6: Final Performance & Stability Audit
- [ ] Task: Benchmarking Dijkstra Performance
    - [ ] Create a test seeder with 20+ nodes to simulate a large network.
    - [ ] Measure and verify Dijkstra execution time remains within simulation tick budget.
- [ ] Task: Create Master Manual Verification Script
    - [ ] Implement `tests/manual/verify_stabilization_v1.php` covering a 10-day cycle with multiple overlapping events.
- [ ] Task: Conductor - User Manual Verification 'Final Performance & Stability Audit' (Protocol in workflow.md)
