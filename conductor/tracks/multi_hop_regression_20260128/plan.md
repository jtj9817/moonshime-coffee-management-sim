# Implementation Plan - Multi-Hop Order Regression Suite

## Phase 1: Foundation & Helper Trait [checkpoint: 272889c]
- [x] Task: Create `Tests\Traits\MultiHopScenarioBuilder`.
    - [x] Create the file `tests/Traits/MultiHopScenarioBuilder.php`.
    - [x] Implement `createVendorPath(array $locations)` helper.
    - [x] Implement `createRoutes(array $routeConfigs)` helper.
    - [x] Implement `createProductBundle(array $products)` helper.
    - [x] Implement `createGameState(User $user, float $cash)` helper.
- [x] Task: Conductor - User Manual Verification 'Phase 1: Foundation & Helper Trait' (Protocol in workflow.md)

## Phase 2: Data Provider Implementation
## Phase 2: Data Provider Implementation [checkpoint: 03d2b9e]
- [x] Task: Refactor `MultiHopOrderTest.php`.
    - [x] Import `Tests\Traits\MultiHopScenarioBuilder`.
    - [x] Implement `scenariosProvider()`:
        - [x] Best Case: Direct vs Multi-hop where multi-hop is cheaper.
        - [x] Average Case: Complex graph with distinct lowest-cost path.
        - [x] Worst Case: Capacity constraints forcing alternative routes.
        - [x] Edge Case: Validation failures (e.g., missing location, zero quantity).
    - [x] Implement `test_multihop_scenarios(array $scenario)`:
        - [x] Use `$this->createVendorPath()`, `$this->createRoutes()`, etc., to setup.
        - [x] Execute order placement via `OrderService` (or endpoint if integration test).
        - [x] Assert results match expected outcomes (Route selection, cost, shipment count).
- [x] Task: Conductor - User Manual Verification 'Phase 2: Data Provider Implementation' (Protocol in workflow.md)

## Phase 3: Verification & Cleanup
- [ ] Task: Execute full regression suite.
    - [ ] Run `php artisan sail --args=pest tests/Feature/MultiHopOrderTest.php`.
    - [ ] Verify all scenarios pass.
    - [ ] Fix any logical discrepancies between spec and implementation.
- [ ] Task: Clean up.
    - [ ] Remove old hardcoded test methods (e.g., `test_can_place_multihop_order`) once covered by the provider.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Verification & Cleanup' (Protocol in workflow.md)
