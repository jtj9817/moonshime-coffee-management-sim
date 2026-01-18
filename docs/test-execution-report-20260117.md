# Test Execution Report - 2026-01-17

## 1. Executive Summary

**Date:** 2026-01-17
**Command Executed:** `php artisan sail --args=pest`
**Total Tests:** 149
**Passed:** 133
**Failed:** 16
**Success Rate:** 89.2%

This report analyzes the failures encountered during the full test suite execution. The failures primarily fall into two categories: function signature mismatches due to recent code changes and logical state inconsistencies in the simulation engine.

---

## 2. Failure Analysis

### 2.1 Category A: Function Signature Mismatches (Critical)
These errors stem from recent refactoring where method signatures were updated in the application code but not in the corresponding tests. These are blocking execution of the logic under test.

#### `App\Actions\GenerateIsolationAlerts::handle`
*   **Error:** `ArgumentCountError: Too few arguments to function... 0 passed... exactly 1 expected`
*   **Context:** The `handle` method now requires an `int $userId` argument, but tests are calling it with no arguments.
*   **Affected Tests:**
    *   `Tests\Unit\Actions\GenerateIsolationAlertsTest` (Lines 31, 51, 67)
    *   `Tests\Feature\IsolationAlertTest` (Lines 41, 77)

#### `App\Events\TimeAdvanced::__construct`
*   **Error:** `ArgumentCountError: Too few arguments to function... 1 passed... exactly 2 expected`
*   **Context:** The `TimeAdvanced` event constructor now requires both `int $day` and `GameState $gameState`, but tests are instantiating it with only `$day`.
*   **Affected Tests:**
    *   `Tests\Unit\Events\EventClassesTest` (Line 45)
    *   `Tests\Unit\Listeners\GenerateSpikeTest` (Lines 21, 36)

---

### 2.2 Category B: Logic & Simulation State Failures
These failures indicate potential bugs in the business logic, simulation state transitions, or incorrect test expectations.

#### DAG Listener Inconsistencies (`Tests\Unit\Listeners\DAGListenersTest`)
*   **Inventory Update Failure:**
    *   **Test:** `update_inventory_listener_updates_stock_on_transfer`
    *   **Result:** Failed asserting that `10` matches expected `60`.
    *   **Analysis:** The listener responsible for finalizing transfers may not be correctly adding the quantity to the target inventory, or the setup state is incorrect.
*   **Cash Deduction Failure:**
    *   **Test:** `deduct_cash_listener_deducts_money_successfully`
    *   **Result:** Failed asserting that `5000` matches expected `3000`.
    *   **Analysis:** The `DeductCash` listener appears to not be persisting the updated cash balance to the `GameState`.
*   **Insufficient Funds Exception:**
    *   **Test:** `deduct_cash_listener_throws_exception_if_insufficient_funds`
    *   **Result:** Failed asserting that exception of type "RuntimeException" is thrown.
    *   **Analysis:** The guard clause for negative cash balance is likely missing or failing.

#### Logistics & Simulation Inconsistencies
*   **Premium Route Identification (`Tests\Feature\LogisticsPremiumRouteTest`):**
    *   **Result:** Failed asserting that `119` is identical to `120`.
    *   **Analysis:** The test expects a specific Route ID to be identified as "premium", but a different one was returned, suggesting the sorting or identification logic in `LogisticsService` needs review.
*   **Simulation Loop Integration (`Tests\Feature\SimulationLoopTest`):**
    *   **Result:** Failed asserting that `false` is `true` (Spike `is_active` check).
    *   **Analysis:** The `SimulationService` might not be correctly triggering spike activation during the time advancement tick.
*   **Order Delivery Processing (`Tests\Feature\SimulationServiceTest`):**
    *   **Result:** Failed asserting that `App\States\Order\Shipped` is instance of `App\States\Order\Delivered`.
    *   **Analysis:** The `ProcessDeliveries` listener (or equivalent logic) failed to transition the order state when the delivery day was reached.

#### State Machine Violations (`Tests\Feature\StateMachinesTest`)
*   **Error:** `RuntimeException: Cannot ship order without a assigned route.`
*   **Context:** The test attempts to transition an order to `Shipped` without ensuring a `route_id` is set on the order model, which is now a strict requirement of the `ToShipped` transition.

---

## 3. Recommendations

1.  **Immediate Remediation (Category A):**
    *   Update all test instantiations of `GenerateIsolationAlerts` to pass a valid User ID (e.g., using a factory-created user).
    *   Update all test instantiations of `TimeAdvanced` to pass the current `GameState` object.

2.  **Investigation & Fix (Category B):**
    *   **DAG Listeners:** Verify `save()` is called on models after modification in listeners.
    *   **Simulation Loop:** Debug the `GenerateSpike` and `ApplySpikeEffect` listeners to ensure they are hooked into the `TimeAdvanced` event correctly.
    *   **State Machines:** Update the failing test to assign a valid Route to the Order before attempting the transition.
