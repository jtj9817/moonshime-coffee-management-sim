# JIRA Ticket: TEST-RELIABILITY-002 - Flaky Seeder DataConsistencyTest Due to Duplicate Location Names

**Type:** Bug  
**Priority:** High  
**Status:** In Progress  
**Assignee:** Unassigned  
**Labels:** `testing`, `flaky-tests`, `seeders`, `locations`, `idempotency`

## Summary
`Tests\Feature\Seeder\DataConsistencyTest` fails intermittently because location names are not guaranteed unique when `GraphSeeder` runs against a non-empty `locations` table.

## Environment
- Laravel test environment (`APP_ENV=testing`)
- Pest + PHPUnit with `RefreshDatabase`
- Logs analyzed from `storage/logs` on 2026-02-11

## Problem Statement
In the most recent 20 log files under `storage/logs`, 5 pest runs failed with the same assertion:

- `tests/Feature/Seeder/DataConsistencyTest.php:52`
- `Failed asserting that 31 is identical to 32.`

This means one duplicate location name exists in the test dataset during those runs.

## Evidence
1. Failing runs (examples):
- `storage/logs/pest-run-20260211-045658-018277.log`
- `storage/logs/pest-run-20260211-045412-625923.log`
- `storage/logs/pest-run-20260211-045341-102294.log`
- `storage/logs/pest-run-20260211-045304-164145.log`
- `storage/logs/pest-run-20260211-045059-214485.log`

2. Seed log evidence of pre-existing locations:
- `storage/logs/game-init-2026-02-11.log:14019`  
  `GraphSeeder: Locations already exist ... {"existing_count":18,...}`

3. Seeder behavior that can create duplicate names:
- `database/seeders/GraphSeeder.php:21` warns on existing locations but proceeds.
- `database/seeders/GraphSeeder.php:40` always creates store with hardcoded name `"Moonshine Central"`.

## Root Cause
`GraphSeeder` is not idempotent with respect to location name uniqueness. When it runs while locations already exist, it still inserts a fresh set of locations, including a fixed-name store (`Moonshine Central`), which can duplicate an existing row and break `DataConsistencyTest` uniqueness assertions.

## Steps to Reproduce
1. Ensure test DB has existing locations (or run a seeding path that creates them first).
2. Run `CoreGameStateSeeder`, then `GraphSeeder` again.
3. Execute `tests/Feature/Seeder/DataConsistencyTest.php`.
4. Observe intermittent failure in `all location names are unique`.

## Actual Result
Test intermittently fails with duplicate name count mismatch (`31 unique` vs `32 total`).

## Expected Result
Location seeding should be deterministic and safe to re-run; uniqueness assertions should pass consistently.

## Proposed Fix
1. Make `GraphSeeder` idempotent for locations:
- Use `firstOrCreate`/`updateOrCreate` with stable identifiers for canonical seeded nodes.
- Avoid unconditional insert of `"Moonshine Central"`.
2. Add a DB-level unique constraint for location names if global uniqueness is required by domain rules.
3. Update seeder tests to validate idempotent re-run behavior explicitly.
4. Keep `DataConsistencyTest` as regression coverage for uniqueness.

## Acceptance Criteria
1. `tests/Feature/Seeder/DataConsistencyTest` passes consistently across repeated runs.
2. Re-running `GraphSeeder` does not increase duplicate location names.
3. `GraphSeeder` behavior is deterministic when locations already exist.
4. Repeat test harness (20 runs) reports 0 failures for this issue.

## Risks and Notes
- If duplicate location names are intentionally allowed by design, then the test must be revised to assert a different invariant.
- If uniqueness is required, enforce it in both seeder logic and schema constraints to prevent regressions.

## Implementation Update (2026-02-11)
### Completed
1. Refactored `GraphSeeder` to use deterministic canonical locations with `updateOrCreate` (idempotent location creation).
2. Replaced route creation in `GraphSeeder` with `updateOrCreate` keyed by (`source_id`, `target_id`, `transport_mode`) to prevent duplicate routes on re-run.
3. Added regression test in `tests/Unit/Seeders/GraphSeederTest.php`:
- `graph seeder is idempotent for locations and routes`
4. Added regression test in `tests/Feature/Seeder/DataConsistencyTest.php`:
- `rerunning graph seeder does not create duplicate location names`

### Solved vs Pending
- Solved: GraphSeeder no longer appends duplicate canonical locations when rerun.
- Solved: Graph route topology is idempotent on repeated seeding.
- Solved: Dedicated regression assertions now check rerun safety for names and route counts.
- Solved: Added migration path to dedupe existing duplicate `locations.name` rows before enforcing uniqueness.
- Solved: Added schema-level unique index plan for `locations.name` to hard-stop future duplicates from any insert path.
- Pending: Execute migration in Sail/Postgres and verify no data conflicts in real environment.
- Pending: Re-run repeat harness (20 runs) after migration to confirm `DataConsistencyTest` no longer flakes.

### Verification Status
- Syntax checks for modified PHP files pass.
- Full test verification is currently blocked in this environment because Docker is not running (`php artisan sail ...` reports: `Docker is not running.`).
- Direct local `php artisan test` is also blocked from valid execution due missing Postgres host (`pgsql`) outside Sail.
- Log re-check shows mixed outcomes:
  - Newer runs at approximately `07:00-07:02` show `Tests\\Feature\\Seeder\\DataConsistencyTest` passing.
  - Earlier runs in the same log window still include intermittent duplicate-name assertions (`31` unique vs `32` total).
