# Track Specification: Phase 0 - Critical Architecture Remediation

## Overview
This track focuses on securing the core architecture of the Moonshine Coffee Management Sim by enforcing two critical invariants: integer-based monetary calculations (cents) and strict user isolation across all gameplay systems. This ensures data integrity and prevents multi-user data leakage before new gameplay features are introduced.

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
- [ ] No monetary calculations in the backend use `float` types for currency.
- [ ] All database columns representing money are `integer` or `bigInteger`.
- [ ] Starting cash is correctly initialized as `1000000` (cents).
- [ ] Automated tests confirm that User A cannot see User B's orders or inventory.
- [ ] Dashboard and Analytics responses are strictly filtered by the authenticated user's ID.
- [ ] Factories and Seeders generate realistic cent-based data.

## Out of Scope
- Implementation of new gameplay features (Demand forecasting, Quest system, etc.).
- Redesigning the frontend UI components beyond currency formatting adjustments.
