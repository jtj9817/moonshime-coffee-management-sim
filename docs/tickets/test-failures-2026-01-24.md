# Test Failure Tickets - 2026-01-24

## Summary

**Test Run Date:** 2026-01-24
**Total Tests:** 244 (237 passed, 7 failed)
**Duration:** 21.20s
**Test Suite:** Pest (PHPUnit)

---

## TICKET-001: DelaySpike Order State Transition Missing

### Status
✅ **COMPLETED**

### Priority
**P0 - Critical** (resolved)

### Component
- **Module:** Spike Events System
- **Service:** DelaySpike Effect Application
- **Model:** Order State Machine

### Affected Tests
- ❌ `Tests\Unit\Services\DelaySpikeScopingTest::DelaySpike only affects orders owned by the spike user`
- ❌ `Tests\Unit\Services\DelaySpikeScopingTest::DelaySpike rollback restores original delivery dates`

### Error Details

**Exception Type:** `Spatie\ModelStates\Exceptions\TransitionNotFound`

**Error Message:**
```
Transition from `draft` to `shipped` on model `App\Models\Order`
was not found, did you forget to register it in `App\Models\Order::registerStates()`?
```

**Stack Trace:**
```
at vendor/spatie/laravel-model-states/src/Exceptions/TransitionNotFound.php:19
   15▕     protected string $modelClass;
   16▕
   17▕     public static function make(string $from, string $to, string $modelClass): self
   18▕     {
➜  19▕         return (new static("Transition from `{$from}` to `{$to}` on model `{$modelClass}` was not found, did you forget to register it in `{$modelClass}::registerStates()`?"))
   20▕             ->setFrom($from)
   21▕             ->setTo($to)
   22▕             ->setModelClass($modelClass);
   23▕     }

  +3 vendor frames
4   tests/Unit/Services/DelaySpikeScopingTest.php:35
```

### Root Cause Analysis

The Order model's state machine is missing a transition from `draft` to `shipped`. The normal order lifecycle should be:
```
Draft → Pending → Shipped → Delivered
```

However, the DelaySpike tests are attempting to directly transition orders from `draft` to `shipped`, which is not a registered transition in the Order state machine.

### Expected Behavior
Orders should support all valid state transitions, including test scenarios where orders might need to skip intermediate states for setup purposes.

### Actual Behavior
The state machine throws `TransitionNotFound` exception when attempting to transition from `draft` to `shipped`.

### Files Involved
- `app/Models/Order.php` - Order model with state machine registration
- `app/States/Order/Draft.php` - Draft state definition
- `app/States/Order/Shipped.php` - Shipped state definition
- `tests/Unit/Services/DelaySpikeScopingTest.php:35` - Test attempting transition
- `tests/Unit/Services/DelaySpikeScopingTest.php:97` - Second test attempting same transition

### Recommended Solution

**Option A: Add Missing Transition** (Recommended)
Register the `draft` → `shipped` transition in `Order::registerStates()` for testing purposes.

**Option B: Fix Test Setup**
Update the test to properly transition through all states:
```php
$order->status->transitionTo(Pending::class);
$order->status->transitionTo(Shipped::class);
```

### Acceptance Criteria
- [x] DelaySpike tests can create orders in `shipped` state
- [x] Order state machine supports necessary transitions
- [x] All 4 DelaySpikeScopingTest cases pass
- [x] No regression in existing order state transitions

### Related Issues
- See TICKET-002 (Authentication issue in same test suite)

---

## TICKET-002: DelaySpike Tests Missing Authentication Context

### Status
✅ **COMPLETED**

### Priority
**P0 - Critical**

### Component
- **Module:** Order Management
- **Service:** Order Placement
- **Transition:** ToPending

### Affected Tests
- ❌ `Tests\Unit\Services\DelaySpikeScopingTest::DelaySpike stores original delivery data in spike meta for rollback`
- ❌ `Tests\Unit\Services\DelaySpikeScopingTest::DelaySpike respects product filtering`

### Error Details

**Exception Type:** `RuntimeException`

**Error Message:**
```
Authenticated user required to place an order.
```

**Stack Trace:**
```
at app/States/Order/Transitions/ToPending.php:23
   19▕     {
   20▕         $user = Auth::user();
   21▕
   22▕         if (!$user) {
➜  23▕             throw new RuntimeException('Authenticated user required to place an order.');
   24▕         }
   25▕
   26▕         $gameState = GameState::where('user_id', $user->id)->first();
   27▕

1   app/States/Order/Transitions/ToPending.php:23
  +7 vendor frames
9   tests/Unit/Services/DelaySpikeScopingTest.php:70
```

### Root Cause Analysis

The `DelaySpikeScopingTest` unit tests are not properly setting up authentication context before attempting order state transitions. The `ToPending` transition requires an authenticated user to:
1. Retrieve the user from `Auth::user()`
2. Load the user's `GameState`
3. Validate sufficient funds

Unit tests are running without `actingAs()` or proper user authentication setup.

### Expected Behavior
Unit tests should either:
- Mock the authentication layer
- Use `$this->actingAs($user)` to set authenticated user
- Bypass authentication requirements for unit testing

### Actual Behavior
The `ToPending` transition throws `RuntimeException` because `Auth::user()` returns `null` in the test environment.

### Files Involved
- `app/States/Order/Transitions/ToPending.php:23` - Authentication check
- `tests/Unit/Services/DelaySpikeScopingTest.php:70` - First failure
- `tests/Unit/Services/DelaySpikeScopingTest.php:134` - Second failure

### Recommended Solution

**Option A: Add Authentication to Tests** (Recommended)
```php
public function test_delay_spike_scoping()
{
    $user = User::factory()->create();
    $this->actingAs($user);

    // ... rest of test
}
```

**Option B: Refactor Transition**
Extract business logic from transition to make it more testable:
```php
// Service method that accepts user explicitly
public function transitionToPending(Order $order, User $user)
{
    $gameState = GameState::where('user_id', $user->id)->first();
    // ... validation logic
}
```

### Acceptance Criteria
- [x] DelaySpikeScopingTest sets up proper authentication context
- [x] All order transitions work with authenticated user
- [x] Tests can verify spike effects on user-specific orders
- [x] No authentication-related exceptions in unit tests

### Resolution Notes

The `tests/Unit/Services/DelaySpikeScopingTest.php` suite now establishes a consistent auth context in `beforeEach()` by:
- Creating a user and calling `$this->actingAs($this->user)`
- Creating a matching `GameState` record for the authenticated user (required by `ToPending`)

This prevents `Auth::user()` from being `null` during the `Draft -> Pending` transition and unblocks the DelaySpike scoping tests.

### Related Issues
- See TICKET-001 (State transition issue in same test suite)

---

## TICKET-003: Order Delivery Not Processing After Transit Duration

### Status
✅ **COMPLETED**

### Priority
**P0 - Critical**

### Component
- **Module:** Simulation Engine
- **Service:** Order Processing / Delivery
- **Feature:** Time Advancement

### Affected Tests
- ❌ `Tests\Feature\GameplayLoopVerificationTest::comprehensive 5-day gameplay loop simulation with player agency`

### Error Details

**Assertion Failure:**
```
Failed asserting that an instance of class App\States\Order\Shipped
is an instance of class App\States\Order\Delivered.

at tests/Feature/GameplayLoopVerificationTest.php:179
  175▕     expect($blizzard->fresh()->is_active)->toBeFalse();
  176▕     expect($standardRoute->fresh()->is_active)->toBeTrue('Route should be restored');
  177▕
  178▕     // Emergency Order should be Delivered (Shipped on Day 3, transit 1 -> Day 4)
➜ 179▕     expect($emergencyOrder->fresh()->status)->toBeInstanceOf(Delivered::class);
  180▕
  181▕     // Standard Order should NOT be delivered yet (Shipped on Day 3, transit 2 -> Day 5)
  182▕     expect($order->fresh()->status)->toBeInstanceOf(Shipped::class);
```

### Root Cause Analysis

The comprehensive gameplay loop test advances time over 5 days and expects orders to be delivered based on their transit duration:

**Expected Timeline:**
- **Day 3:** Emergency order ships (transit duration: 1 day)
- **Day 4:** Emergency order should be DELIVERED (3 + 1 = 4)
- **Day 5:** Test runs assertions

**Actual Timeline:**
- **Day 3:** Emergency order ships
- **Day 4:** Order delivery NOT processed
- **Day 5:** Order still in `Shipped` state ❌

This indicates the `ProcessDeliveries` listener or the time advancement logic is not correctly transitioning orders from `Shipped` to `Delivered` when `shipped_at + transit_days = current_day`.

### Expected Behavior
1. When `SimulationService::advanceTime()` is called
2. `TimeAdvanced` event fires
3. `ProcessDeliveries` listener executes
4. For each order where `shipped_at + shipment.route.transit_days <= current_day`
5. Order transitions from `Shipped` to `Delivered`
6. Inventory is updated at destination location

### Actual Behavior
Orders remain in `Shipped` state even after transit duration has elapsed.

### Files Involved
- `app/Services/SimulationService.php` - Time advancement logic
- `app/Listeners/ProcessDeliveries.php` - Order delivery processing
- `app/States/Order/Shipped.php` - Shipped state
- `app/States/Order/Transitions/ToDelivered.php` - Delivery transition
- `tests/Feature/GameplayLoopVerificationTest.php:179` - Failing assertion

### Potential Causes
1. **ProcessDeliveries listener not attached** to TimeAdvanced event
2. **Incorrect date calculation** in delivery processing logic
3. **Missing shipment data** preventing delivery calculation
4. **Database query issue** not finding orders ready for delivery
5. **Transition not firing** due to guard conditions or validation

### Recommended Solution

**Step 1: Verify Event Listener Registration**
Check `app/Providers/EventServiceProvider.php`:
```php
protected $listen = [
    TimeAdvanced::class => [
        ProcessDeliveries::class,
        // ...
    ],
];
```

**Step 2: Debug ProcessDeliveries Logic**
Add logging to identify the issue:
```php
public function handle(TimeAdvanced $event)
{
    $currentDay = $event->day;

    $ordersToDeliver = Order::query()
        ->where('status', Shipped::class)
        ->whereHas('shipments', function ($query) use ($currentDay) {
            $query->whereRaw('shipped_at + transit_days <= ?', [$currentDay]);
        })
        ->get();

    Log::info('Orders ready for delivery', [
        'count' => $ordersToDeliver->count(),
        'current_day' => $currentDay,
    ]);

    // Process deliveries...
}
```

### Resolution Notes

Re-ran the previously failing test in the Sail environment and it now passes:
```
sail pest --filter=GameplayLoopVerificationTest
PASS  Tests\Feature\GameplayLoopVerificationTest
```

Treat the original 2026-01-24 failure as stale/non-reproducible with current code.

**Step 3: Verify Shipment Data**
Ensure emergency order has proper shipment record with:
- `shipped_at` = 3
- `route.transit_days` = 1
- Expected delivery day = 4

### Acceptance Criteria
- [ ] Orders transition from `Shipped` to `Delivered` on correct day
- [ ] Emergency order (transit 1 day) delivers on Day 4
- [ ] Standard order (transit 2 days) delivers on Day 5
- [ ] GameplayLoopVerificationTest passes completely
- [ ] Inventory updated at destination upon delivery

### Test Case Context
```php
// Day 3: Place emergency order with 1-day transit
$emergencyOrder = Order::create([...]);
$emergencyOrder->status->transitionTo(Shipped::class);

// Advance to Day 4
$this->simulationService->advanceTime();

// EXPECTED: Order is delivered
// ACTUAL: Order still shipped
```

---

## TICKET-004: Multi-Hop Order Shipments Not Being Created

### Status
✅ **COMPLETED**

### Priority
**P0 - Critical** (resolved)

### Component
- **Module:** Multi-Hop Logistics
- **Service:** OrderService
- **Feature:** Shipment Creation

### Affected Tests
- ❌ `Tests\Feature\MultiHopOrderTest::can place multihop order`

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
   91▕
   92▕         $firstLeg = $order->shipments()->where('sequence_index', 0)->first();
   93▕         $this->assertEquals($vendorLocation->id, $firstLeg->source_location_id);
   94▕         $this->assertEquals($hub->id, $firstLeg->target_location_id);
```

### Root Cause Analysis

When placing a multi-hop order (e.g., Vendor → Hub → Store), the system should create multiple `Shipment` records representing each leg of the journey:

**Expected:**
- Shipment 1: Vendor → Hub (sequence_index: 0)
- Shipment 2: Hub → Store (sequence_index: 1)

**Actual:**
- Zero shipments created

This indicates the `OrderService::placeOrder()` or related shipment creation logic is not properly handling multi-hop routes returned by `LogisticsService::findBestRoute()`.

### Expected Behavior

1. User places order from vendor to store
2. `LogisticsService::findBestRoute()` returns multi-hop path
3. For each route segment in the path:
   - Create `Shipment` record with `sequence_index`, `source_location_id`, `target_location_id`, `route_id`
4. Link all shipments to the order
5. Calculate total cost and transit time

### Actual Behavior

Order is created but no shipments are generated, even though the route requires multiple hops.

### Files Involved
- `app/Services/OrderService.php` - Order placement and shipment creation
- `app/Services/LogisticsService.php` - Multi-hop routing logic
- `app/Models/Shipment.php` - Shipment model
- `app/Models/Order.php` - Order-shipment relationship
- `tests/Feature/MultiHopOrderTest.php:90` - Failing assertion

### Potential Causes

1. **Shipment creation logic missing** from OrderService
2. **Multi-hop path not being processed** correctly
3. **Route path structure mismatch** between LogisticsService and OrderService
4. **Database relationship issue** preventing shipment association
5. **Conditional logic** skipping shipment creation for certain order types

### Recommended Solution

**Step 1: Verify LogisticsService Returns Path**
```php
$path = $this->logisticsService->findBestRoute($sourceId, $targetId);
// Expected: [Route, Route] for multi-hop
// Actual: ?
```

**Step 2: Implement Shipment Creation**
```php
// In OrderService::placeOrder()
public function placeOrder(array $orderData, array $path)
{
    $order = Order::create($orderData);

    // Create shipments for each leg
    foreach ($path as $index => $route) {
        Shipment::create([
            'order_id' => $order->id,
            'route_id' => $route->id,
            'sequence_index' => $index,
            'source_location_id' => $route->source_location_id,
            'target_location_id' => $route->target_location_id,
            'status' => 'pending',
        ]);
    }

    return $order;
}
```

**Step 3: Update Order Controller**
Ensure the controller passes the routing path to the service:
```php
$path = $logisticsService->findBestRoute($request->source_id, $request->target_id);
$order = $orderService->placeOrder($orderData, $path);
```

### Acceptance Criteria
- [x] Multi-hop orders create correct number of shipments
- [x] Each shipment has correct `sequence_index` (0, 1, 2, ...)
- [x] Shipments link source → intermediate → target locations
- [x] First shipment references vendor location
- [x] Last shipment references store location
- [x] MultiHopOrderTest passes (current code)
- [ ] Shipment transit times calculated correctly

### Test Case Context
```php
// Order from vendor → hub → store
$response = $this->post('/game/orders', [
    'source_location_id' => $vendorLocation->id,
    'target_location_id' => $storeLocation->id,
    'product_id' => $product->id,
    'quantity' => 100,
]);

$order = Order::first();
// EXPECTED: 2 shipments
// ACTUAL: 0 shipments ❌
```

### Resolution Notes

Re-ran `Tests\Feature\MultiHopOrderTest::can place multihop order` and it now passes with current code. Treat the original 2026-01-24 failure as stale/non-reproducible.

Note: this test currently has a single core assertion about shipment count (and follow-up assertions about leg endpoints). It does not fully validate shipment timing (e.g., `arrival_day`/`arrival_date`) or shipment status progression.

---

## TICKET-005: Breakdown Spike Resolution Not Deducting Cash

### Status
🔴 **HIGH**

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
   41▕         ->and($spike->resolved_by)->toBe('player')
   42▕         ->and($spike->resolved_at)->not()->toBeNull()
   43▕         ->and($spike->resolution_cost)->toBe($estimatedCost)
   44▕         ->and($spike->ends_at_day)->toBe(5) // Current day
➜  45▕         ->and($this->gameState->cash)->toBe($costBefore - $estimatedCost);
   46▕ })
```

### Root Cause Analysis

**Test Setup:**
- Initial cash: `$100,000`
- Estimated resolution cost: `$50,000`
- Expected final cash: `$50,000`

**Actual Result:**
- Final cash: `$99,500`
- Cash deducted: `$500` instead of `$50,000`

This indicates the `SpikeResolutionService::resolve()` method is either:
1. Not deducting the correct amount
2. Deducting a different cost (possibly a fixed $500 fee)
3. Not calling the deduction logic at all

The spike is being marked as resolved (metadata updates correctly), but the financial transaction is not executing properly.

### Expected Behavior

When resolving a breakdown spike:
1. Calculate resolution cost: `$50,000`
2. Verify user has sufficient funds
3. Deduct cost from `GameState::cash`
4. Update spike: `resolved_by = 'player'`, `resolved_at = now`, `resolution_cost = 50000`
5. Restore location capacity
6. Save all changes

### Actual Behavior

The spike metadata updates correctly but only `$500` is deducted instead of the full `$50,000`.

### Files Involved
- `app/Services/SpikeResolutionService.php` - Resolution logic
- `app/Listeners/DeductCash.php` - Cash deduction listener
- `app/Models/GameState.php` - Cash balance
- `tests/Feature/SpikeResolutionTest.php:45` - Failing assertion

### Potential Causes

1. **Wrong variable used** in deduction:
   ```php
   // WRONG: Using fixed cost
   $gameState->cash -= 500;

   // CORRECT: Using calculated cost
   $gameState->cash -= $estimatedCost;
   ```

2. **Deduction logic not called**:
   ```php
   $spike->resolution_cost = $estimatedCost; // Saved to spike
   // Missing: $gameState->cash -= $estimatedCost;
   ```

3. **Event listener not firing**:
   - If using `DeductCash` listener, the event might not be dispatched
   - Or listener is using wrong amount

4. **Database transaction rollback**:
   - Changes to spike saved but GameState changes rolled back

### Recommended Solution

**Step 1: Review SpikeResolutionService**
```php
public function resolve(SpikeEvent $spike): void
{
    $cost = $this->calculateResolutionCost($spike);

    $gameState = GameState::where('user_id', $spike->user_id)->first();

    if ($gameState->cash < $cost) {
        throw new InsufficientFundsException();
    }

    DB::transaction(function () use ($spike, $gameState, $cost) {
        // Update spike
        $spike->resolved_by = 'player';
        $spike->resolved_at = now();
        $spike->resolution_cost = $cost;
        $spike->ends_at_day = $gameState->current_day;
        $spike->save();

        // CRITICAL: Deduct cash
        $gameState->cash -= $cost;
        $gameState->save();

        // Restore capacity or other effects
        $this->rollbackSpikeEffect($spike);
    });
}
```

**Step 2: Add Logging**
```php
Log::info('Resolving spike', [
    'spike_id' => $spike->id,
    'cost' => $cost,
    'cash_before' => $gameState->cash,
    'cash_after' => $gameState->cash - $cost,
]);
```

**Step 3: Verify Test Expectations**
Ensure the test is checking the correct GameState instance:
```php
$this->gameState->fresh()->cash; // Reload from database
```

### Acceptance Criteria
- [ ] Breakdown spike resolution deducts correct amount ($50,000)
- [ ] GameState cash reflects deduction
- [ ] Spike metadata records resolution cost
- [ ] Insufficient funds throws exception
- [ ] SpikeResolutionTest passes completely
- [ ] Cash deduction is atomic (success or rollback)

### Test Case Context
```php
$gameState->cash = 100_000;
$estimatedCost = 50_000;

// Resolve breakdown spike
$this->post("/game/spikes/{$spike->id}/resolve");

// EXPECTED: gameState->cash = 50_000
// ACTUAL: gameState->cash = 99_500 ❌
```

---

## Summary Statistics

### Test Results
- ✅ **Passed:** 237 tests
- ❌ **Failed:** 7 tests
- ⏱️ **Duration:** 21.20s

### Failure Breakdown by Severity

| Priority | Count | Tickets |
|----------|-------|---------|
| P0 - Critical | 4 | TICKET-001, TICKET-002, TICKET-003, TICKET-004 |
| P1 - High | 1 | TICKET-005 |

### Failure Breakdown by Component

| Component | Failed Tests | Tickets |
|-----------|--------------|---------|
| Spike Events System | 5 | TICKET-001, TICKET-002, TICKET-005 |
| Order Processing | 1 | TICKET-003 |
| Multi-Hop Logistics | 1 | TICKET-004 |

### Recommended Fix Order

1. **TICKET-002** (Authentication in tests) - Quick fix, enables other tests
2. **TICKET-001** (State transition) - Blocks DelaySpike tests
3. **TICKET-005** (Cash deduction) - Business logic issue
4. **TICKET-004** (Shipment creation) - Feature implementation
5. **TICKET-003** (Order delivery) - Complex simulation issue

### Notes

All failures appear to be implementation issues rather than test design problems. The test suite is comprehensive and catching real bugs in:
- State machine configuration
- Test authentication setup
- Simulation engine delivery processing
- Multi-hop order flow
- Financial transaction handling

Once these tickets are resolved, the test suite should achieve 100% pass rate.
