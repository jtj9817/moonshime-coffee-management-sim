# Implementation Plan - Multi-Hop Order Regression Suite

## Phase 1: Foundation & Helper Trait
- [x] Task: Create `Tests\Traits\MultiHopScenarioBuilder`.
    - [x] Create the file `tests/Traits/MultiHopScenarioBuilder.php`.
    - [x] Implement `createVendorPath(array $locations)` helper.
    - [x] Implement `createRoutes(array $routeConfigs)` helper.
    - [x] Implement `createProductBundle(array $products)` helper.
    - [x] Implement `createGameState(User $user, float $cash)` helper.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Foundation & Helper Trait' (Protocol in workflow.md)

## Phase 2: Data Provider Implementation
- [ ] Task: Refactor `MultiHopOrderTest.php`.
    - [ ] Import `MultiHopScenarioBuilder` trait.
    - [ ] Create the `scenariosProvider` method.
    - [ ] Populate `scenariosProvider` with "Best Case" scenarios (from `docs/multi-hop-order-test-scenarios.md` → **Concrete Scenario Data Table (Filled Values)**).
    - [ ] Populate `scenariosProvider` with "Average Case" scenarios (lowest-cost route must be unambiguous).
    - [ ] Populate `scenariosProvider` with "Worst Case" scenarios (ensure capacity equals quantity, not exceeding).
    - [ ] Populate `scenariosProvider` with "Edge/Negative" scenarios (explicit validation field expectations).
- [ ] Task: Implement the dynamic test method.
    - [ ] Create `test_multihop_scenarios` method consuming the data provider.
    - [ ] Implement setup logic using the builder trait.
    - [ ] Implement execution logic (placing the order).
    - [ ] Implement assertions (Success vs. Failure, lowest-cost path selection, per-leg shipment counts).
    - [ ] Assert validation fields: `location_id`, `items`, `total` per scenario.
    - [ ] Pass `source_location_id` explicitly in all scenario payloads.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Data Provider Implementation' (Protocol in workflow.md)

## Phase 3: Verification & Cleanup
- [ ] Task: Execute full regression suite.
    - [ ] Run `php artisan sail --args=pest tests/Feature/MultiHopOrderTest.php`.
    - [ ] Verify all scenarios pass.
    - [ ] Fix any logical discrepancies between spec and implementation.
- [ ] Task: Clean up.
    - [ ] Remove old hardcoded test methods (e.g., `test_can_place_multihop_order`) once covered by the provider.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Verification & Cleanup' (Protocol in workflow.md)
