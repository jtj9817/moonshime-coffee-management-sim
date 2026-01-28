# Specification: Multi-Hop Order Regression Suite

## Overview
This track involves refactoring and expanding the `tests/Feature/MultiHopOrderTest.php` file into a comprehensive regression suite. It will leverage the scenarios defined in `docs/multi-hop-order-test-scenarios.md` to ensure the multi-hop ordering logic is robust against various configurations, performance limits, and edge cases.

## Functional Requirements
- **Comprehensive Scenario Coverage**: Implement test cases for:
    - **Best Case**: Simple, successful 2-hop chains.
    - **Average Case**: 3-4 hops with mixed transport modes and multiple products.
    - **Worst Case**: Long chains (6-10 hops), tight cash, and capacity limits.
    - **Edge/Negative Cases**: No viable routes, inactive legs, cycles, insufficient cash, capacity exceeded, and source equals target.
- **Data-Driven Testing**: Use a PHPUnit/Pest data provider to execute all scenarios through a centralized test method, ensuring consistent assertion logic across variations.
- **Reusable Test Helpers**: Extract setup logic (creating locations, routes, products, and game state) into a dedicated trait `Tests\Traits\MultiHopScenarioBuilder`.

## Non-Functional Requirements
- **Determinism**: All tests must be deterministic. Avoid random values in fixtures.
- **Performance**: Ensure that even the "Worst Case" scenarios (10+ hops) run efficiently within the test suite.
- **Maintainability**: The use of a trait and data provider should make it easy to add new scenarios in the future.

## Acceptance Criteria
- [ ] `MultiHopOrderTest.php` is refactored to use a data provider for all scenarios listed in `docs/multi-hop-order-test-scenarios.md`.
- [ ] A new trait `Tests\Traits\MultiHopScenarioBuilder` is created and used for setting up test data.
- [ ] All "Best", "Average", and "Worst" cases result in successful orders with correct totals and shipment sequences.
- [ ] All "Edge" and "Negative" cases result in appropriate validation errors and do not persist data (no orders or shipments created).
- [ ] Total cost calculations (items + logistics) are verified to 2 decimal places.
- [ ] Shipment sequences are verified for continuity (Target A == Source B).

## Out of Scope
- Implementation of new routing algorithms (this track is for testing existing logic).
- UI/Frontend testing for multi-hop orders.
- Performance benchmarking beyond standard test execution.
