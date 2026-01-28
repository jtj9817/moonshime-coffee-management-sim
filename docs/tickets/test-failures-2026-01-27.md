# Test Failure Tickets - 2026-01-27

## Summary

**Test Run Date:** 2026-01-27
**Total Tests:** 247 (244 passed, 3 failed)
**Duration:** 18.68s
**Test Suite:** Pest (PHPUnit)
**Run Command:** sail test

---

## TICKET-001: Multi-Hop Order Shipments Missing In Suite

### Status
✅ **COMPLETE**

### Priority
**P1 - High**

### Component
- **Module:** Multi-Hop Logistics
- **Service:** OrderService / LogisticsService
- **Feature:** Shipment Creation

### Affected Tests
- ✅ `Tests\Feature\MultiHopOrderTest::can place multihop order`

### Error Details

**Assertion Failure:**
```
Failed asserting that actual size 0 matches expected size 2.

at tests/Feature/MultiHopOrderTest.php:90
   86▕         ]);
   87▕
   88▕         // Assert Shipments Created
   89▕         $order = \App\Models\Order::first();
➜  90▕         $this->assertCount(2, $order->shipments);
```

### Initial Observations
- The test passes when run alone (`--filter=MultiHopOrderTest`)
- The full suite run reports zero shipments for the created order

### Potential Causes (Initial)
1. Path resolution returning an unexpected/empty collection during full suite execution
2. Order lookup returning a different order than the one created by the request
3. State leakage or cached routing data influencing the resolved path

### Resolution
- Updated the test to select the order created by the request (scoped by user/vendor/location) before asserting shipments, eliminating suite-order ambiguity.

### Files Involved
- `tests/Feature/MultiHopOrderTest.php:90`
- `app/Http/Controllers/GameController.php:285`
- `app/Http/Requests/StoreOrderRequest.php:58-160`
- `app/Services/OrderService.php:37-129`
- `app/Services/LogisticsService.php:73-170`

### Recommended Next Steps
- None. Ticket closed.

---

## TICKET-002: Reset Game Fails When Seed Data Is Missing

### Status
🟡 **INVESTIGATING**

### Priority
**P0 - Critical** (blocks reset flow)

### Component
- **Module:** Game Initialization
- **Action:** InitializeNewGame
- **Controller:** GameController@resetGame

### Affected Tests
- ❌ `Tests\Feature\ResetGameTest::authenticated user can reset game`

### Error Details

**Exception Type:** `RuntimeException`

**Error Message:**
```
Cannot initialize game: No stores found. Please ensure GraphSeeder has been run.
```

**Stack Trace (excerpt):**
```
RuntimeException: Cannot initialize game: No stores found. Please ensure GraphSeeder has been run.
  at app/Actions/InitializeNewGame.php:108
  at app/Http/Controllers/GameController.php:414
  at tests/Feature/ResetGameTest.php:15
```

### Root Cause Analysis (Initial)
`InitializeNewGame::seedInitialInventory()` requires `Location` records for stores and warehouses. The test does not seed `GraphSeeder` or `CoreGameStateSeeder`, so the `locations` table is empty when the reset route is called. In some runs this passes if data was already present, but it fails in a fresh database run.

### Files Involved
- `tests/Feature/ResetGameTest.php:7-30`
- `app/Actions/InitializeNewGame.php:97-132`
- `app/Http/Controllers/GameController.php:390-421`

### Recommended Next Steps
- Seed `GraphSeeder` and `CoreGameStateSeeder` in the test
- Or mock `InitializeNewGame` when testing the reset endpoint
- Or update the reset flow to handle missing seed data gracefully

---

## TICKET-003: Breakdown Spike Resolution Cash Deduction Mismatch

### Status
🟡 **INVESTIGATING**

### Priority
**P1 - High**

### Component
- **Module:** Spike Events
- **Service:** SpikeResolutionService
- **Feature:** Cash Deduction

### Affected Tests
- ❌ `Tests\Feature\SpikeResolutionTest::can resolve breakdown spike early and deduct cost`

### Error Details

**Assertion Failure:**
```
Failed asserting that 99500.0 is identical to 50000.0.

at tests/Feature/SpikeResolutionTest.php:45
```

### Root Cause Analysis (Initial)
The test expectation appears to compare values using different units (cents vs. dollars) after the 2026-01-21 money column conversion. The system likely deducts dollar values, while the test expects a cent-based result.

### Files Involved
- `tests/Feature/SpikeResolutionTest.php:41-46`
- `app/Services/SpikeResolutionService.php`
- `app/Listeners/DeductCash.php`
- `database/migrations/2026_01_21_000000_convert_money_columns_to_decimal.php`

### Recommended Next Steps
- Confirm expected units for `resolution_cost` and `game_states.cash`
- Align test expectation with stored units (dollars)

---

## Summary Statistics

### Test Results
- ✅ **Passed:** 244 tests
- ❌ **Failed:** 3 tests
- ⏱️ **Duration:** 18.68s

### Failure Breakdown by Component

| Component | Failed Tests | Tickets |
|-----------|--------------|---------|
| Multi-Hop Logistics | 1 | TICKET-001 |
| Game Initialization | 1 | TICKET-002 |
| Spike Events | 1 | TICKET-003 |

---

## Supplementary Analysis (2026-01-27)

### TICKET-001 (Multi-Hop Order Shipments Missing In Suite)
- **First-principles path:** POST `/game/orders` → middleware → `StoreOrderRequest` validation → `_calculated_path` computed → `OrderService::createOrder()` → `createShipmentsForOrder()`.
- **Observed symptom:** order exists, but `order->shipments` returns 0 in suite only.
- **Most likely explanation:** the path being passed into `OrderService` is empty or mismatched in the full suite run, so `createShipmentsForOrder()` iterates over zero legs. In isolation, `_calculated_path` resolves correctly.
- **Why suite-only:** shared state between tests (route graph cache, container-resolved service instances, or prior tests altering active routes/spikes) can change `LogisticsService::findBestRoute()` output. If a stale adjacency cache exists, it can omit newly created routes in this test.
- **Secondary risk:** `Order::first()` is ambiguous. If any other order is created in the same test flow (e.g., via initialization helpers), the assertion can target the wrong order. Prefer selecting by vendor/location or by ID returned from the request.

### TICKET-002 (Reset Game Fails When Seed Data Is Missing)
- **First-principles path:** POST `/game/reset` → `GameController::resetGame()` → `InitializeNewGame::handle()` → `seedInitialInventory()` → requires `Location`(store/warehouse) + `Product` + `Vendor`.
- **Failure mode:** with a fresh test database, the required seed data is absent, so `InitializeNewGame` throws `RuntimeException` and the request returns 500.
- **Why it looks random:** the test implicitly depends on external seed state. If any prior run seeded Graph/Core data and the DB wasn't freshly migrated, the test passes; with a clean DB (full suite via `RefreshDatabase`), it fails deterministically.
- **Stabilization:** seed `GraphSeeder` + `CoreGameStateSeeder` inside the test, or mock `InitializeNewGame` so the endpoint can be exercised without global seed dependencies.

### TICKET-003 (Breakdown Spike Resolution Cash Deduction Mismatch)
- **Unit mismatch remains the most likely cause.** The test expectation appears to mix cents vs. dollars after the money-column conversion migration. Align expected values with the stored unit.

### Suggested Next Steps
1. Add a targeted assertion in `MultiHopOrderTest` to fetch the order by ID or vendor/location instead of `Order::first()`.
2. Log or assert `_calculated_path` size in `StoreOrderRequest` during the test to verify route resolution is stable.
3. In tests that mutate route availability, clear `LogisticsService` cache or resolve a fresh instance before `findBestRoute()`.
4. Update `ResetGameTest` to seed `GraphSeeder` and `CoreGameStateSeeder` (or mock `InitializeNewGame`) to remove dependency on global seed state.
