# JIRA Ticket: TEST-RELIABILITY-001 — Flaky Test Failures in Repeat Sail/Pest Runs

**Type:** Bug  
**Priority:** High  
**Status:** Open  
**Assignee:** Unassigned  
**Labels:** `testing`, `flaky-tests`, `simulation`, `spike-system`, `location-ownership`

## Goal
Eliminate non-deterministic test outcomes observed in repeat Sail/Pest execution by isolating random simulation side effects and cross-test database leakage, then validating determinism with scenario-driven coverage.

## Problem Summary
The most recent 20 repeat-run logs (`storage/logs/pest-run-*.log`) show intermittent failures in otherwise passing suites:

- Full suite failed in 8/20 runs.
- Focused suite failed in 4/20 runs.
- Recurring failures:
  - `Tests\Unit\Models\UserTest` (6 occurrences)
  - `Tests\Feature\ScheduledOrderServiceTest` (4 occurrences)
  - `Tests\Feature\GameplayLoopVerificationTest` (2 occurrences)
  - `Tests\Feature\SimulationServiceTest` (1 occurrence)

## Failure Signatures
1. `tests/Unit/Models/UserTest.php:26`  
   `Failed asserting that 4 is identical to 3.`

2. `tests/Feature/ScheduledOrderServiceTest.php:89`  
   `Failed asserting that 2510|2790|2890|3190 is identical to 2250.`

3. `tests/Feature/SimulationServiceTest.php:60` and `tests/Feature/GameplayLoopVerificationTest.php:178`  
   Expected `Delivered`, actual `Shipped`.

## Technical Root Cause Analysis

### Issue A — Scheduled-order totals are affected by random active price spikes
**Category:** Flaky assertion / uncontrolled randomness  
**Primary Impacted Tests:**
- `tests/Feature/ScheduledOrderServiceTest.php`

**Involved Code:**
- `app/Services/SimulationService.php`
- `app/Services/GuaranteedSpikeGenerator.php`
- `app/Services/SpikeEventFactory.php`
- `app/Services/OrderService.php`
- `app/Services/PricingService.php`

**Root Cause Detail:**
- `SimulationService::advanceTime()` always executes event tick before planning tick.
- Event tick may generate/activate random spikes on the same day.
- If a `price` spike is active, `OrderService` recalculates scheduled-order line item pricing via `PricingService::getPriceMultiplierFor()`.
- The test expects a fixed total (`2250`) but actual totals vary based on random multipliers.

**Fix Direction:**
- Make tests deterministic by controlling spike generation (e.g., fake/mocked generator/factory in targeted tests) or disabling spike mutation in tests that validate order math.
- Keep one dedicated integration test that explicitly verifies spike-adjusted pricing behavior.

---

### Issue B — Delivery assertions are affected by random delay spikes
**Category:** Flaky state transition / temporal drift  
**Primary Impacted Tests:**
- `tests/Feature/SimulationServiceTest.php`
- `tests/Feature/GameplayLoopVerificationTest.php`

**Involved Code:**
- `app/Services/SimulationService.php`
- `app/Services/SpikeEventFactory.php`
- `app/Services/Spikes/DelaySpike.php`

**Root Cause Detail:**
- During `advanceTime()`, delay spikes can be generated/activated before physics processing.
- `DelaySpike::apply()` mutates `delivery_day` for pending/shipped orders.
- Assertions expecting day-bound delivery transitions fail when `delivery_day` is pushed forward.

**Fix Direction:**
- For delivery-state tests, freeze or stub spike generation to exclude delay events.
- Add explicit delay-spike behavior tests that assert shifted delivery timelines separately.

---

### Issue C — User location sync test is polluted by leaked vendor locations
**Category:** Cross-test data leakage / fixture coupling  
**Primary Impacted Tests:**
- `tests/Unit/Models/UserTest.php`

**Involved Code:**
- `app/Models/User.php`
- `tests/Unit/Listeners/GenerateSpikeTest.php`
- `database/factories/SpikeEventFactory.php`
- `database/factories/LocationFactory.php`

**Root Cause Detail:**
- `User::syncLocations()` intentionally attaches all vendor locations.
- `GenerateSpikeTest` creates `SpikeEvent::factory()->make()` without DB isolation trait.
- `SpikeEventFactory` creates related `Location::factory()`, and `LocationFactory` randomizes type including `vendor`.
- If leaked vendor rows persist into later tests, `syncLocations()` attaches one extra location and expected count `3` becomes `4`.

**Fix Direction:**
- Ensure DB isolation for unit tests that instantiate model factories with relational subfactories.
- Make listener test avoid accidental persisted relations (or explicitly use non-vendor location state).
- Update `UserTest` setup to constrain vendor fixtures to known data where appropriate.

## Proposed Implementation Tasks
1. Stabilize `ScheduledOrderServiceTest` by controlling spike generation path during `advanceTime()` in that suite.
2. Stabilize delivery transition tests by isolating them from random delay spikes.
3. Add explicit spike-influence tests so deterministic tests do not lose domain coverage.
4. Add/standardize DB isolation (`RefreshDatabase`) for affected unit tests with relational factories.
5. Remove unintended vendor creation side effects in non-target tests.
6. Re-run repeat harness and ensure no intermittent failures across 20 consecutive runs.

## Validation Scenarios

### Issue A Scenarios (Scheduled-order total determinism)
**A1. Baseline fixed-price scheduled auto-submit**
- Setup: No active spikes; deterministic route cost and unit price.
- Action: Advance simulation to due day.
- Expectation: `total_cost` remains fixed at expected cents and cash deduction matches.

**A2. Explicit active price spike multiplier**
- Setup: Inject active `price` spike scoped to user/product.
- Action: Advance simulation and process due schedule.
- Expectation: `total_cost` reflects multiplier; assertion uses computed deterministic expected value.

**A3. Non-price spike should not alter order total**
- Setup: Active `delay` or `blizzard` spike only, no price spike.
- Action: Advance simulation on schedule day.
- Expectation: `total_cost` equals baseline expected amount.

**A4. Vendor-scoped price spike isolation**
- Setup: Two vendors, active price spike scoped to vendor A only.
- Action: Execute schedule for vendor B.
- Expectation: No multiplier applied to vendor B scheduled order total.

**A5. Repeat determinism check for scheduled-order suite**
- Setup: Execute affected test file repeatedly under same fixture controls.
- Action: Run repeated suite (e.g., 20 consecutive runs).
- Expectation: 0 failures; no random total-cost drift.

### Issue B Scenarios (Delivery-state determinism)
**B1. Baseline delivery transition without spikes**
- Setup: Shipped order with `delivery_day = current_day + 1`, no active spikes.
- Action: Advance simulation one day.
- Expectation: Status transitions to `Delivered`.

**B2. Delay spike shifts delivery day**
- Setup: Active delay spike magnitude `+N`, shipped order due next day.
- Action: Advance simulation through original due day.
- Expectation: Order remains `Shipped`; `delivery_day` is incremented by `N`.

**B3. Post-delay eventual delivery**
- Setup: Same as B2.
- Action: Advance simulation until shifted `delivery_day`.
- Expectation: Order transitions to `Delivered` exactly on shifted day.

**B4. Gameplay loop deterministic path with controlled spike types**
- Setup: Multi-day gameplay loop fixture with explicit blizzard only, delay generation disabled.
- Action: Run 5-day simulation assertions.
- Expectation: Emergency and standard orders meet expected delivery states by defined days.

**B5. Repeat determinism check for delivery-focused tests**
- Setup: Controlled spike generation in `SimulationServiceTest` and `GameplayLoopVerificationTest`.
- Action: Repeat target tests multiple times.
- Expectation: 0 intermittent `Delivered` vs `Shipped` failures.

### Issue C Scenarios (User location sync isolation)
**C1. Sync attaches only known store + known vendors**
- Setup: One inventory store + two explicit vendor locations.
- Action: Call `syncLocations()`.
- Expectation: Attached count and final owned location IDs match exact fixture set.

**C2. Idempotent second sync**
- Setup: Same as C1 after first sync.
- Action: Call `syncLocations()` again.
- Expectation: Attached count is `0`; no duplicates.

**C3. Incremental sync after adding new store and vendor**
- Setup: Post-initial sync, add one new inventory location and one new vendor.
- Action: Call `syncLocations()`.
- Expectation: Attached count increments by exactly `2`.

**C4. Listener factory isolation guard**
- Setup: Execute `GenerateSpikeTest` in isolation with DB reset.
- Action: Inspect location/vendor counts before and after.
- Expectation: No persistent leaked vendor rows outside test boundary.

**C5. Cross-suite repeat for user sync test**
- Setup: Run `UserTest` after listener-related unit tests in repeated order.
- Action: Execute repeated sequence and/or full repeat harness.
- Expectation: `UserTest` remains stable with expected count `3` in every run.

## Acceptance Criteria
- No flaky failures for the three issue groups across repeat execution.
- Deterministic tests use explicit fixtures/mocks for spike-sensitive behavior.
- Spike behavior remains covered via dedicated explicit tests.
- `User::syncLocations()` tests pass consistently without cross-test contamination.
