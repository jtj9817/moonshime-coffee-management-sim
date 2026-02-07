# Implementation Plan: Phase 0 - Critical Architecture Remediation

This plan implements Phase 0 from `docs/gameplay-features-implementation-spec.md` and is limited to architecture invariants: monetary unit canonicalization and global user isolation.

## Phase 1: Monetary Unit Canonicalization (Cents) [checkpoint: 6f4a23c]

### Current State (Verified)
- [x] `InitializeNewGame` uses `1000000` starting cash.
- [x] `HandleInertiaRequests` fallback game-state creation uses `1000000`.
- [x] Monetary semantics are now fully standardized to integer cents across models and domain logic.

### Task 1: Audit Monetary Columns and Casts
- [x] Audit current money columns and units in schema and model casts.
- [x] Verify/standardize casts for:
  - [x] `game_states.cash`
  - [x] `orders.total_cost`
  - [x] `products.unit_price`
  - [x] `demand_events.unit_price`
- [x] Update `app/Models/GameState.php` cast for `cash` to integer cents.

### Task 2: Domain Arithmetic Standardization
- [x] Audit and standardize cent-based arithmetic in:
  - [x] `app/Listeners/DeductCash.php`
  - [x] `app/Listeners/ApplyStorageCosts.php`
  - [x] `app/States/Order/Transitions/ToPending.php`
  - [x] `app/Http/Requests/StoreOrderRequest.php`
  - [x] `app/Services/DemandSimulationService.php`
- [x] Remove float-based currency math from backend domain logic.

### Task 3: Boundary Conversion Contract
- [x] Document and enforce a single conversion boundary:
  - [x] Persistence/business logic = integer cents.
  - [x] Backend serialization/frontend formatting = display dollars.
- [x] Verify `resources/js/lib/formatCurrency.ts` is only used for display formatting, not business logic conversion.

### Task 4: Seed/Test Data Consistency
- [x] Audit factories and seeders for cent-consistent money values.
- [x] Ensure no create/reset path initializes cash with `10000.00`.

### Task 5: Verification (Money Invariants)
- [x] Keep/verify `tests/Feature/GameInitializationTest.php`.
- [x] Add targeted assertions that new game cash remains `1000000` cents.
- [x] Run regression suite for financial flow integrity.

## Phase 2: Global User Isolation Audit

### Current State (Verified)
- [x] Middleware shared props are user-scoped for alerts, reputation, and strikes.
- [x] Gameplay controllers and derived aggregates are now fully scoped by authenticated user.

### Task 1: Controller and Aggregate Query Audit
- [x] Audit `app/Http/Controllers/GameController.php` for per-user table queries.
- [x] Enforce explicit scoping for all per-user entities:
  - [x] `alerts` (authorization check on markAlertRead)
  - [x] `orders` (ordering page scoped)
  - [x] `transfers` (transfers page scoped)
  - [x] `inventory` (inventory page + SKU detail scoped)
  - [x] `spike_events` (already scoped in spikeHistory, now also in LogisticsService/Controller)
  - [x] `demand_events` (already scoped in analytics)
  - [x] `daily_reports` (already scoped in dashboard)
  - [x] `game_states` (already scoped throughout)
- [x] Ensure analytics/reporting aggregates are scoped by authenticated user.

### Task 2: Automated Isolation Tests
- [x] Add or update feature tests for multi-user isolation:
  - [x] User A cannot read User B dashboard data (inventory, orders, transfers, SKU detail).
  - [x] User A cannot list User B orders/transfers/inventory.
  - [x] Vendor pages only show authenticated user's order counts/metrics.
  - [x] Alert authorization prevents cross-user access.
  - [x] Logistics route spike effects are user-scoped.
- [x] Maintain middleware-shared-prop isolation checks.

### Task 3: Verification (Isolation Invariants)
- [x] Confirm no dashboard/list/analytics query returns another user's data.
- [x] Run feature test suite to ensure scoping changes do not regress valid flows (282 tests pass).

## Phase 3: Phase 0 Exit Validation

### Verification Matrix (Must Pass)
- [ ] **Money**
  - [ ] No game creation/reset path initializes cash with `10000.00`.
  - [ ] Starting cash invariant remains `1000000` cents.
  - [ ] Monetary casts and arithmetic are cent-based in backend domain logic.
- [ ] **Isolation**
  - [ ] Dashboard/list/analytics responses are user-scoped.
  - [ ] Shared middleware props and page-specific props both enforce user isolation.
- [ ] **Regression Coverage**
  - [ ] `tests/Feature/GameInitializationTest.php` remains green.
  - [ ] Multi-user isolation feature coverage is present and green.

### Final Quality Checks
- [ ] Run `php artisan sail --args=pest`.
- [ ] Run `php artisan sail --args=pint`.
- [ ] Run `php artisan sail --args=pnpm --args=lint`.
