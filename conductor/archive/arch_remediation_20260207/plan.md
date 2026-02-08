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

## Phase 2: Global User Isolation Audit [checkpoint: 4073f50]

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

## Phase 2.5: Coverage Hardening for Phase 0 Invariants (Additive)

### Current State (Audit Snapshot)
- [x] **High Finding:** Frontend automated test harness now exists for React/Inertia components (`Vitest`/`RTL` with `*.test.*` files).
- [x] **Medium Finding:** Targeted direct backend coverage added for:
  - [x] `app/Listeners/ApplyStorageCosts.php`
  - [x] `app/Services/LogisticsService.php` (`forUser()` context behavior)
  - [x] `app/Http/Requests/StoreOrderRequest.php` (request-contract-focused assertions)
- [x] This phase is additive hardening only and does **not** change completion status of Phases 1, 2, or 3.

### Task 1: Frontend Test Harness Foundation (Addresses High Finding)
- [x] Add frontend unit test stack for React/Inertia:
  - [x] Add `vitest` + `@testing-library/react` + `@testing-library/jest-dom` + `jsdom`.
  - [x] Add test scripts in `package.json` (e.g., `test`, `test:watch`).
  - [x] Add test config + setup file (global matchers, DOM cleanup).
- [x] Provide deterministic mocks/helpers for Inertia-specific runtime concerns:
  - [x] Router/navigation mocks.
  - [x] Shared prop injection patterns for page-level tests.
  - [x] Any route helper shims used by page components.

### Task 2: Frontend Regression Tests for Phase 0 Surfaces (Addresses High Finding)
- [x] Add targeted component/page tests for currency boundary and scoped rendering behavior in:
  - [x] `resources/js/lib/formatCurrency.ts` (display formatting contract only).
  - [x] `resources/js/pages/game/dashboard.tsx` (cash/KPI rendering from backend props).
  - [x] `resources/js/pages/game/vendors.tsx` and `resources/js/pages/game/vendors/detail.tsx` (scoped metrics rendering).
  - [x] `resources/js/pages/game/analytics.tsx` (overview and scoped aggregate display).
  - [x] `resources/js/pages/game/inventory.tsx`, `resources/js/pages/game/ordering.tsx`, `resources/js/pages/game/transfers.tsx` (critical UI states fed by scoped backend props).
- [x] Add assertions that frontend does not perform business-logic money conversion (formatting-only boundary remains intact).

### Task 3: Backend Targeted Gap Closure (Addresses Medium Findings)
- [x] Add focused tests for `app/Listeners/ApplyStorageCosts.php`:
  - [x] Confirms storage deduction arithmetic is integer-cent based.
  - [x] Confirms deductions apply to the correct user scope.
- [x] Add focused tests for `app/Services/LogisticsService.php`:
  - [x] Explicitly validate `forUser()` scoping changes cost/path outcomes when spikes belong to different users.
  - [x] Validate cache invalidation when switching user context.
- [x] Add focused tests for `app/Http/Requests/StoreOrderRequest.php`:
  - [x] Validate cents-based input/normalization and totals contract.
  - [x] Validate route-capacity and insufficient-funds branches with precise assertions.
  - [x] Validate missing-path/no-route error handling behavior.

### Task 4: Verification and Quality Gates
- [~] Run backend suite with targeted filters for new tests and full regression pass. (Blocked in this environment: Docker not running and local Postgres host `pgsql` unavailable.)
- [~] Run frontend lint/types and new test suite in Sail workflow. (`vitest` suite and targeted `eslint` pass locally; `pnpm types` fails on pre-existing project-wide TypeScript errors unrelated to this phase.)
- [x] Update Phase 0 verification matrix notes to include frontend component-test coverage status once passing.

## Phase 3: Phase 0 Exit Validation [checkpoint: fa8079e]

### Verification Matrix (Must Pass)
- [x] **Money**
  - [x] No game creation/reset path initializes cash with `10000.00`.
  - [x] Starting cash invariant remains `1000000` cents.
  - [x] Monetary casts and arithmetic are cent-based in backend domain logic.
- [x] **Isolation**
  - [x] Dashboard/list/analytics responses are user-scoped.
  - [x] Shared middleware props and page-specific props both enforce user isolation.
- [x] **Regression Coverage**
  - [x] `tests/Feature/GameInitializationTest.php` remains green.
  - [x] Multi-user isolation feature coverage is present and green.
  - [x] Frontend component-test coverage exists for Phase 0 UI surfaces (currency display boundary + scoped rendering pages).

### Final Quality Checks
- [x] Run `./vendor/bin/sail pest` — 282 passed (1426 assertions).
- [x] Run `./vendor/bin/sail php ./vendor/bin/pint` on changed files — all clean.
- [x] Run `sail pnpm run lint` (manually verified by user).
