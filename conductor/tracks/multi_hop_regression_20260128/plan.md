# Implementation Plan - Multi-Hop Order Regression Suite

## Phase 1: Foundation & Helper Trait
- [ ] Task: Create `Tests\Traits\MultiHopScenarioBuilder`.
    - [ ] Create the file `tests/Traits/MultiHopScenarioBuilder.php`.
    - [ ] Implement `createVendorPath(array $locations)` helper.
    - [ ] Implement `createRoutes(array $routeConfigs)` helper.
    - [ ] Implement `createProductBundle(array $products)` helper.
    - [ ] Implement `createGameState(User $user, float $cash)` helper.
- [ ] Task: Conductor - User Manual Verification 'Phase 1: Foundation & Helper Trait' (Protocol in workflow.md)

## Phase 2: Data Provider Implementation
- [ ] Task: Refactor `MultiHopOrderTest.php`.
    - [ ] Import `MultiHopScenarioBuilder` trait.
    - [ ] Create the `scenariosProvider` method.
    - [ ] Populate `scenariosProvider` with "Best Case" scenarios (from `docs/multi-hop-order-test-scenarios.md`).
    - [ ] Populate `scenariosProvider` with "Average Case" scenarios.
    - [ ] Populate `scenariosProvider` with "Worst Case" scenarios.
    - [ ] Populate `scenariosProvider` with "Edge/Negative" scenarios.
- [ ] Task: Implement the dynamic test method.
    - [ ] Create `test_multihop_scenarios` method consuming the data provider.
    - [ ] Implement setup logic using the builder trait.
    - [ ] Implement execution logic (placing the order).
    - [ ] Implement assertions (Success vs. Failure, Cost checks, Shipment counts).
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Data Provider Implementation' (Protocol in workflow.md)

## Phase 3: Verification & Cleanup
- [ ] Task: Execute full regression suite.
    - [ ] Run `php artisan sail --args=pest tests/Feature/MultiHopOrderTest.php`.
    - [ ] Verify all scenarios pass.
    - [ ] Fix any logical discrepancies between spec and implementation.
- [ ] Task: Clean up.
    - [ ] Remove old hardcoded test methods (e.g., `test_can_place_multihop_order`) once covered by the provider.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Verification & Cleanup' (Protocol in workflow.md)
