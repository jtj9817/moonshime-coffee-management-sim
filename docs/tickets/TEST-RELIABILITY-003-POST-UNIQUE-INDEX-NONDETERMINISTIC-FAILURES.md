# JIRA Ticket: TEST-RELIABILITY-003 - Post-Index Non-Deterministic Test Failures

**Type:** Bug  
**Priority:** Highest  
**Status:** Open  
**Assignee:** Unassigned  
**Labels:** `testing`, `flaky-tests`, `determinism`, `factories`, `postgres`

## Summary
After resolving `DataConsistencyTest` and enforcing `locations.name` uniqueness, the suite still has non-deterministic failures. The dominant class is now `UniqueConstraintViolationException` on `locations_name_unique` from test-created locations, plus one stochastic threshold failure in demand simulation.

## Scope and Impact
- Blocks reliable CI confidence for core Feature and Unit coverage.
- Causes intermittent red runs without product-code regressions.
- Indicates test data generation and isolation guarantees are still incomplete.

## Evidence (Latest Log Window)
Analysis date: `2026-02-11`  
Window: latest 20 files in `storage/logs` (18 pest logs + 2 non-test logs)

- Pest logs analyzed: `18`
- Failed pest logs: `9`
- `DataConsistencyTest` failures in this window: `0`
- Remaining failures:
  - `locations_name_unique` violations: `8` logs
  - stochastic demand assertion failure: `1` log

Representative failing logs:
- `storage/logs/pest-run-20260211-224641-758710.log`
- `storage/logs/pest-run-20260211-224532-587913.log`
- `storage/logs/pest-run-20260211-224426-469916.log`
- `storage/logs/pest-run-20260211-224321-385062.log`
- `storage/logs/pest-run-20260211-224140-963296.log`
- `storage/logs/pest-run-20260211-223957-181832.log`
- `storage/logs/pest-run-20260211-223818-925489.log`
- `storage/logs/pest-run-20260211-223744-792472.log`
- `storage/logs/pest-run-20260211-223851-759680.log` (simulation threshold assertion)

## Failure Inventory

### A) Unique Constraint Violations (`locations_name_unique`)
Observed exceptions:
- `SQLSTATE[23505]: Unique violation ... duplicate key value violates unique constraint "locations_name_unique"`
- Example duplicate values from logs:
  - `Schmitt Imports`
  - `Lockman Imports`
  - `Bogisich Imports`
  - `Bahringer Imports`

Impacted tests (examples from logs):
- `Tests\Feature\InitialSpikeSeederTest` (`tests/Feature/InitialSpikeSeederTest.php:18`)
- `Tests\Feature\EventPropagationTest` (`tests/Feature/EventPropagationTest.php:13`)
- `Tests\Feature\StoreOrderRequestTest` (`tests/Feature/StoreOrderRequestTest.php:23`)
- `Tests\Feature\ScheduledOrderServiceTest` (`tests/Feature/ScheduledOrderServiceTest.php:33`)
- `Tests\Feature\CoreSchemaTest` (`tests/Feature/CoreSchemaTest.php:21`)
- `Tests\Feature\LogisticsRoutesTest` (`tests/Feature/LogisticsRoutesTest.php:43`)
- `Tests\Feature\ScheduledOrderControllerTest` (`tests/Feature/ScheduledOrderControllerTest.php:25`)
- `Tests\Unit\Services\LogisticsServiceUserContextTest` (`tests/Unit/Services/LogisticsServiceUserContextTest.php:17`)

### B) Stochastic Threshold Flake
Impacted test:
- `Tests\Feature\SpikeSimulationTest` (`tests/Feature/SpikeSimulationTest.php:206`)

Failure:
- `Failed asserting that 40 is greater than 40.`

Interpretation:
- Current expectation is strict `> 40`, but simulated outcome can land exactly `40` under allowed variance.

## Bug Analysis

### 1. [FILES INVOLVED]
- `database/factories/LocationFactory.php`
- `tests/Pest.php`
- `tests/Feature/SpikeSimulationTest.php`
- Unit DB-writing tests missing isolation trait:
  - `tests/Unit/Services/DelaySpikeScopingTest.php`
  - `tests/Unit/Services/GuaranteedSpikeGeneratorTest.php`
  - `tests/Unit/Services/SimulationServiceTest.php`
  - `tests/Unit/Services/SpikeConstraintCheckerTest.php`

### 2. [FUNCTIONS/COMPONENTS INVOLVED]
- Factory name generation:
  - `LocationFactory::generateName()` (`database/factories/LocationFactory.php:37`)
- Global test DB isolation configuration:
  - Feature suite uses `RefreshDatabase` in `tests/Pest.php:15`
  - Unit suite has no global `RefreshDatabase` binding in `tests/Pest.php`
- Flaky demand assertion:
  - multi-day simulation assertion in `tests/Feature/SpikeSimulationTest.php:206`

### 3. [CODE INVOLVED]
- Factory uniqueness currently relies on Faker uniqueness:
  - `database/factories/LocationFactory.php:50-54` uses `$this->faker->unique()...`
- Multiple tests create locations through factory default names without deterministic seed or explicit naming.
- Some Unit tests perform DB writes but do not use `RefreshDatabase`, so run-order contamination is possible.

### 4. [ROOT CAUSE ANALYSIS]
1. **Factory-level uniqueness is probabilistic, not a hard DB-safe contract**
   - `Faker->unique()` only guarantees uniqueness within that generator scope/lifecycle.
   - Across the full suite, collisions can still occur, which now surface immediately due the enforced unique index.

2. **Incomplete database isolation between tests**
   - Feature tests are globally wrapped with `RefreshDatabase`, but Unit tests are not.
   - Unit tests writing to DB can leave state behind, creating cross-suite interference and collision pressure on factory-generated location names.

3. **Non-deterministic simulation assertion boundary**
   - `SpikeSimulationTest` uses randomized consumption and asserts strict `> 40`.
   - Boundary value `40` is valid under current variance model, causing intermittent false negatives.

## Remediation Plan

### Workstream 1: Hard Deterministic Location Naming for Tests
1. Update `LocationFactory` test-time naming strategy to append deterministic uniqueness (e.g., ULID/sequence suffix) instead of relying only on Faker uniqueness.
2. Keep semantic prefixes (`Coffee`, `Distribution Hub`, `Depot`, `Imports`) but guarantee DB-unique names.
3. Add dedicated regression test proving `Location::factory()->count(N)->create()` cannot violate `locations_name_unique` under repeated runs.

### Workstream 2: Close DB Isolation Gaps in Unit Suite
1. Apply `RefreshDatabase` (or equivalent) to Unit tests that write to DB:
   - `DelaySpikeScopingTest`
   - `GuaranteedSpikeGeneratorTest`
   - `SimulationServiceTest`
   - `SpikeConstraintCheckerTest`
2. Decide whether to enforce globally in `tests/Pest.php` for `Unit`, or local trait usage for DB-touching Unit files only.
3. Add lint/check rule (or CI guard) to detect Unit tests that call `factory()->create` without DB reset trait.

### Workstream 3: Make Spike Simulation Assertion Deterministic
1. Replace strict boundary assertion at `tests/Feature/SpikeSimulationTest.php:206` with a deterministic invariant.
2. Prefer one of:
   - seed randomness and assert exact expected range,
   - inject controllable random source in `DemandSimulationService`,
   - or adjust assertion to include boundary (`>= 40`) if 40 is valid by design.
3. Document expected variance model in test comments so threshold choices remain explicit.

## Acceptance Criteria
1. Repeated run harness (20 consecutive full-suite runs) reports `0` failures.
2. No `locations_name_unique` violations across repeated runs.
3. No intermittent `SpikeSimulationTest` threshold failures.
4. `DataConsistencyTest` continues to pass in repeated runs.
5. CI pipeline has deterministic pass/fail behavior independent of test execution order.

## Verification Commands
- `php artisan sail --args=pest`
- `php artisan sail --args=pest --args=--filter=SpikeSimulationTest`
- `php artisan sail --args=pest --args=--filter=LogisticsServiceUserContextTest`
- Repeat harness against full suite (20x) and inspect latest `storage/logs/pest-run-*.log`.

## Notes
- `TEST-RELIABILITY-002` is resolved for GraphSeeder idempotency and DataConsistency coverage.
- This ticket tracks the next failure class introduced/revealed after enforcing schema-level uniqueness.
