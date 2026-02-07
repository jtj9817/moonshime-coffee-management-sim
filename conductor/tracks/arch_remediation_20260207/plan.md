# Implementation Plan: Phase 0 - Critical Architecture Remediation

This plan outlines the steps to standardize monetary units to integer cents and enforce strict user isolation across the application.

## Phase 1: Monetary Unit Canonicalization (Cents)

### Task 1: Audit and Migration
- [ ] Task: Audit database migrations for currency columns (cents vs dollars).
- [ ] Task: Create migration to standardize any `decimal` or `float` currency columns to `bigInteger`.
    - [ ] Target: `game_states.cash`
    - [ ] Target: `orders.total_price`, `orders.unit_price`
    - [ ] Target: `products.base_price`
    - [ ] Target: `vendors.base_cost`
- [ ] Task: Update `GameState` model to cast `cash` as `integer`.

### Task 2: Domain Logic Standardization
- [ ] Task: Audit `app/Services/SimulationService.php` for cent-based arithmetic.
- [ ] Task: Audit `app/Listeners/DeductCash.php` and financial listeners.
- [ ] Task: Standardize `app/Actions/InitializeNewGame.php` starting cash to `1000000`.

### Task 3: Test Data and Frontend Boundaries
- [ ] Task: Update all Model Factories to use integer cents for financial fields.
- [ ] Task: Update Seeders to align with cent-based defaults.
- [ ] Task: Verify `resources/js/lib/formatCurrency.ts` correctly handles cents-to-dollars conversion.

### Task 4: Verification (Money)
- [ ] Task: Write unit tests to ensure `SimulationService` calculations are precise (no floats).
- [ ] Task: Run `php artisan sail --args=pest` to check for regressions in financial flows.
- [ ] Task: Conductor - User Manual Verification 'Phase 1: Monetary Canonicalization' (Protocol in workflow.md)

## Phase 2: Global User Isolation Audit

### Task 1: Controller and Query Audit
- [ ] Task: Audit `app/Http/Controllers/GameController.php` for missing `user_id` scopes.
- [ ] Task: Audit `app/Http/Controllers/OrderController.php` and `TransferController.php`.
- [ ] Task: Ensure all aggregate queries (analytics/reports) include `where('user_id', auth()->id())`.

### Task 2: Automated Isolation Testing
- [ ] Task: Create a Feature Test `tests/Feature/UserIsolationTest.php`.
    - [ ] Test: User A cannot access User B's GameState.
    - [ ] Test: User A cannot list User B's Orders.
    - [ ] Test: User A cannot view User B's Inventory.
- [ ] Task: Verify Middleware shared props in `app/Http/Middleware/HandleInertiaRequests.php`.

### Task 3: Verification (Isolation)
- [ ] Task: Run full test suite to ensure scoping doesn't break existing functionality.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: User Isolation' (Protocol in workflow.md)

## Phase 3: Final Integration & Cleanup

### Task 1: Quality Assurance
- [ ] Task: Run `php artisan sail --args=pint` for PHP linting.
- [ ] Task: Run `php artisan sail --args=pnpm --args=lint` for JS linting.
- [ ] Task: Final regression test run.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Final Integration' (Protocol in workflow.md)
