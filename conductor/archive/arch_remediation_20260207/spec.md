# Track Specification: Phase 0 - Critical Architecture Remediation

## Overview
This track focuses on securing the core architecture of the Moonshine Coffee Management Sim by enforcing two critical invariants: integer-based monetary calculations (cents) and strict user isolation across all gameplay systems. This ensures data integrity and prevents multi-user data leakage before new gameplay features are introduced.

## Current State Snapshot
- **Verified:** Startup cash initialization uses `1000000` in the primary new-game and middleware fallback paths.
- **Verified:** Middleware shared props scope alerts/reputation/strikes by authenticated user.
- **Pending:** Monetary logic still mixes cent and float-dollar semantics in several backend paths.
- **Pending:** Gameplay controller and aggregate-query user scoping still requires full audit.

## Functional Requirements

### 1. Monetary Unit Canonicalization
- **Strict Integer Cents:** Audit and convert all monetary fields (cash, prices, costs, valuations) to use integer cents in persistence and business logic.
- **Model Casts:** Update Eloquent model casts to ensure currency fields are treated as integers (or custom currency cast if implemented).
- **Arithmetic Audit:** Verify that all financial calculations (revenue, COGS, storage fees, etc.) are performed using integer arithmetic to avoid floating-point precision errors.
- **Boundary Formatting:** Ensure that conversion to display dollars ($X.XX) occurs only at the frontend boundary or through a standardized formatting layer.
- **Data Consistency:** Audit database migrations, factories, and seeders to ensure all default and generated values align with the 1,000,000 cent starting cash invariant.

### 2. Global User Isolation
- **Explicit Scoping:** Manually audit and enforce `user_id` scoping on all queries within gameplay controllers and derived aggregates.
- **Scoping Pattern:** Use the explicit pattern: `$query->where('user_id', auth()->id())` to ensure transparency and developer intent.
- **Affected Entities:** Ensure isolation for `alerts`, `orders`, `transfers`, `inventory`, `spike_events`, `demand_events`, `daily_reports`, and `game_states`.

## Non-Functional Requirements
- **Performance:** Scoping queries by `user_id` should utilize existing database indexes to maintain high performance.
- **Safety:** No gameplay endpoint should ever return data belonging to another user.

## Acceptance Criteria
- [ ] No game creation/reset path initializes cash with `10000.00`.
- [ ] Starting cash is correctly initialized as `1000000` (cents).
- [ ] Monetary casts and domain arithmetic are cent-based in backend logic.
- [ ] Dashboard/list/analytics responses are strictly filtered by authenticated user ID.
- [ ] Shared middleware props and page-specific props both enforce user isolation.
- [ ] Automated tests confirm multi-user isolation for dashboard and gameplay aggregates.
- [ ] Factories and seeders generate cent-consistent financial values.

## Out of Scope
- Implementation of new gameplay features (Demand forecasting, Quest system, etc.).
- Redesigning the frontend UI components beyond currency formatting adjustments.
