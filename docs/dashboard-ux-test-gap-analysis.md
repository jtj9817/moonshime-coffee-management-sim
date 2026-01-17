# Dashboard UX & Test Scenario Gap Analysis

## Overview

This document analyzes the current dashboard user experience (UX) and maps it against the comprehensive 5-day gameplay loop verification test (`tests/Feature/GameplayLoopVerificationTest.php`). It identifies gaps where the UI does not fully support the game mechanics tested and provides recommendations for alignment.

**Project:** Moonshine Coffee Management Sim  
**Stack:** Laravel 12 + React 19 + Inertia.js 2.0 + TailwindCSS  
**Date:** 2026-01-17

---

## Table of Contents

- [Context](#context)
- [Current Dashboard UX Architecture](#current-dashboard-ux-architecture)
- [Test Scenario Mapping](#test-scenario-mapping)
- [Day One Expectations](#day-one-expectations)
- [Critical Gaps](#critical-gaps)
- [Recommendations](#recommendations)

---

## Context

### Gameplay Loop Verification Test

The test (`GameplayLoopVerificationTest.php`) simulates a comprehensive 5-day gameplay scenario with the following key phases:

**Day 1:** Player places initial order via standard route (Truck, 2-day transit)

**Day 2:** Blizzard disruption activates, blocking standard route. Player must:
- Respond to disruption via logistics engine suggestions
- Place emergency order via premium route (Air, 1-day transit)

**Day 3:** Orders ship; transit times apply

**Day 4:** Emergency order delivers; blizzard ends; standard route restored

**Day 5:** Standard order delivers; inventory verified

**Additional Mechanics Tested:**
- Order cancellation (prevented on delivered orders, allowed on shipped with refunds)
- Route capacity validation (orders exceeding capacity fail)
- Cash flow tracking throughout simulation

### Test Assertions Summary

```php
// Cash flow verification
- Starting cash: $1,000,000
- After initial order: $990,000 (-$10,000)
- After emergency order: $970,000 (-$20,000)
- Final after all deductions: $968,000

// State transitions verified
- Order: Draft → Pending → Shipped → Delivered
- Spike events activate/deactivate routes
- Refunds on cancellation
- Capacity validation exceptions
```

---

## Current Dashboard UX Architecture

### Page Structure

```
/game/dashboard
├── Active Spike Alert Banner (conditional)
├── KPI Cards (5 metrics)
├── Location Status Cards (3 locations)
└── Active Quests (gamification)

Quick Actions Panel:
├── Restock → /game/ordering
├── Balance → /game/transfers
├── Audit → /game/inventory
└── Forecast → /game/analytics
```

### Key Components

| Component | File | Props/State | Actions |
|-----------|------|-------------|---------|
| Dashboard | `pages/game/dashboard.tsx` | `alerts`, `kpis`, `quests`, `logistics_health`, `active_spikes_count` | N/A |
| Ordering | `pages/game/ordering.tsx` | `orders`, `vendorProducts` | "New Order" button |
| Transfers | `pages/game/transfers.tsx` | `transfers`, `suggestions` | Route path finding |
| Game Context | `contexts/game-context.tsx` | `gameState`, `locations`, `products`, `vendors`, `alerts`, `currentSpike` | `advanceDay()`, `refreshData()`, `markAlertRead()` |

### Data Flow

```
Laravel Controllers (GameController.php)
         ↓ Inertia props
React Pages (dashboard.tsx, ordering.tsx, etc.)
         ↓
Game Context (useGame hook)
         ↓
UI Components display state
         ↓
User actions → Inertia router → Laravel routes → Business logic
```

### Services

- `cockpitService.ts` - Alert generation, KPIs, suggested actions
- `spikeService.ts` - Demand spike detection, emergency options
- `LogisticsController` - Route path finding, health checks

---

## Test Scenario Mapping

### Scenario 1: Day 1 - Initial Order

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Place order via standard route | `/game/ordering` page has "New Order" button | ⚠️ Partial - No route selection UI |
| Order deducts cash | Orders table shows `total_cost` | ✅ Matches |
| Order status transitions | Orders table shows status badges (Draft, Pending, Shipped, Delivered) | ✅ Matches |

**Gap:** While the Dashboard effectively creates **Decision Pressure** (showing empty stock and critical alerts), the UX fails at **Execution**. The "New Order" button is a dead-end with no route selection (Standard Truck vs Premium Air) or cost-benefit analysis UI. This prevents the player from exercising the agency required by the test.

---

### Scenario 2: Day 2 - Blizzard Disruption

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Display active spike event | Dashboard shows `Active Spike Alert Banner` when `currentSpike` exists | ✅ Matches |
| Show route is blocked | `/game/transfers` has route awareness with blocked status | ✅ Matches |
| Logistics engine suggests alternative | Transfers page shows path finding with "Direct Route Blocked" message | ✅ Matches |

---

### Scenario 3: Day 2 - Emergency Order

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Player places emergency order via Air route | No explicit "emergency order" flow or premium route selection in `/game/ordering` | ❌ Missing |
| Higher cost for premium route | Orders table shows `total_cost` but doesn't differentiate premium vs standard | ❌ Missing |

**Gap:** Test expects player to choose Premium Air route explicitly. Current UX doesn't expose route selection or costs per route.

---

### Scenario 4: Day 3 - Orders Ship

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Order status changes to "Shipped" | Orders table displays status | ✅ Matches |
| Delivery day calculated | Orders table shows `delivery_day` column | ✅ Matches |

---

### Scenario 5: Day 4 - Restoration & Delivery

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Blizzard ends | `currentSpike` becomes null, banner disappears | ✅ Matches |
| Route restored | Transfers page shows "Route Active" | ✅ Matches |
| Emergency order delivered | Order status becomes "Delivered" | ✅ Matches |

---

### Scenario 6: Day 5 - Final Verification

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Standard order delivered | Order status becomes "Delivered" | ✅ Matches |
| Inventory updated | `/game/inventory` shows quantities | ✅ Matches |
| Cash flow tracked | No cash display on dashboard (only in KPIs if calculated) | ⚠️ Partial |

---

### Scenario 7: Cancellation & Refunds

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Cannot cancel delivered orders | No cancel button visible anywhere | ❌ Missing |
| Cancel shipped order for refund | No cancel button visible anywhere | ❌ Missing |
| Refund cash to player | Cash not displayed prominently | ❌ Missing |

**Critical Gap:** Entire cancellation workflow is absent from UI. Test verifies state machine prevents illegal cancellations and refunds valid ones, but UI provides no way to trigger this.

---

### Scenario 8: Route Capacity Validation

| Test Requirement | Dashboard UX | Status |
|-----------------|---------------|--------|
| Order exceeding route capacity fails | No capacity check visible in UI | ❌ Missing |
| Error message on capacity exceeded | No validation error displayed | ❌ Missing |

**Gap:** Test expects explicit capacity validation. Transfers page has route info but doesn't show capacity limits or validate against them.

---

## Day One Expectations

### Current State: The "Clean Slate" Orientation

On Day 1, the Dashboard should function as a setup phase that orients the player toward their first procurement action. It should create **Situational Awareness** that leads naturally to a decision:

1. **Clear Starting Capital:** $1,000,000 prominently displayed to signal "Investment Phase."
2. **Critical "Zero Stock" Indicators:** Every location should show a warning or critical border due to empty inventory.
3. **KPI Pressure:** "Low Stock Items" should be red/down-trending immediately.
4. **Targeted Quests:** A "Stock Champion" quest provides the target value (e.g., 100 units) to aim for.
5. **Clean Logistics:** No active spikes, signaling that the "Standard Route" is currently safe and optimal.

### Current Reality: Awareness vs. Execution

The dashboard successfully creates the **need** to act, but the UX breaks down at the point of **completing** the act.

| Component | UX Role | Actual Behavior |
|----------|---------|-----------------|
| **Header** | Finance/Time Tracking | Shows Day 1 and $1M, correctly mapping to test start. |
| **KPI Cards** | Decision Pressure | Shows red trends for inventory, successfully creating urgency. |
| **Location Cards** | Situational Awareness | Correctly flags "Central Warehouse" as critical. |
| **Ordering Page** | Decision Execution | **The Dead-End Button:** The "New Order" button is non-functional. |
| **Route Selection**| Decision Agency | No UI exists to choose Standard vs. Premium routes. |

### Suggested Day One UX Improvements

```tsx
// Add to dashboard.tsx

<DayCounter currentDay={gameState.day} totalDays={5} />

{gameState.day === 1 && (
  <WelcomeBanner>
    <h2>Welcome, Manager!</h2>
    <p>Your coffee empire begins today. Place your first order to stock all locations.</p>
    <Button to="/game/ordering">Place First Order</Button>
  </WelcomeBanner>
)}

<CashDisplay amount={gameState.cash} />
```

---

## Critical Gaps Summary

### Priority 1: Blocking Test Validation

| Gap | Impact | Test Failing |
|-----|--------|--------------|
| **The Dead-End Button** | Player cannot actually place orders in the Procurement Center. | All Scenarios |
| **No Route Comparison** | Cannot choose between Standard (Truck) and Premium (Air) routes. | Scenario 1, 3 |
| **No Order Cancellation UI** | Cannot verify cancellation or refund mechanics. | Scenario 7 |
| **No Capacity Validation UI** | Cannot verify capacity enforcement or see limits. | Scenario 8 |

### Priority 2: Missing Day Progression Feedback

| Gap | Impact | UX Issue |
|-----|--------|----------|
| No day counter visible | Player cannot track simulation progress | All scenarios |
| Cash not displayed prominently | Cannot verify test assertions about cash flow | Scenario 6 |
| No explicit "Advance Day" action | `advanceDay()` exists but no UI trigger | All scenarios |

### Priority 3: Onboarding/Guidance

| Gap | Impact | UX Issue |
|-----|--------|----------|
| No Day 1 welcome message | Confusing for new players | Day 1 experience |
| Empty locations show "Systems Normal" | Misleading when inventory is empty | Day 1 experience |
| No first-quest guidance | Player unsure where to start | Day 1 experience |

---

## Recommendations

### 1. Add Route Selection to Orders UI

**File:** `pages/game/ordering.tsx`

```tsx
// Add route picker component
<RoutePicker 
  sourceLocationId={order.source_id}
  targetLocationId={order.location_id}
  selectedRouteId={order.route_id}
  onRouteChange={setRoute}
/>
```

**Backend Route:** `/game/logistics/routes?source_id=X&target_id=Y`
**Returns:** Available routes with costs, transit days, capacity, weather vulnerability

### 2. Add Order Cancellation Workflow

**File:** `pages/game/ordering.tsx`

```tsx
<TableRow>
  {order.status === 'shipped' && (
    <Button onClick={() => cancelOrder(order.id)} variant="destructive">
      Cancel & Refund
    </Button>
  )}
</TableRow>
```

**Backend Route:** `POST /game/orders/{id}/cancel`
**Validates:** Order state (not delivered), processes refund

### 3. Add Day Counter & Cash Display

**File:** `pages/game/dashboard.tsx`

```tsx
<div className="flex items-center gap-6 mb-6">
  <DayCounter day={gameState.day} maxDays={5} />
  <CashDisplay amount={gameState.cash} />
  <AdvanceDayButton onAdvance={advanceDay} />
</div>
```

### 4. Add Route Capacity Validation

**File:** `pages/game/ordering.tsx` or `pages/game/transfers.tsx`

```tsx
{routeInfo && (
  <div>
    <RouteCapacity used={currentCapacity} max={route.capacity} />
    {exceedsCapacity && (
      <Alert severity="error">
        Order exceeds route capacity by {excess} units
      </Alert>
    )}
  </div>
)}
```

### 5. Implement Day 1 Onboarding

**File:** `pages/game/dashboard.tsx`

```tsx
{gameState.day === 1 && !hasFirstOrder && (
  <OnboardingWizard>
    <Step>Review your locations</Step>
    <Step>Place initial order</Step>
    <Step>Choose delivery route</Step>
  </OnboardingWizard>
)}
```

### 6. Add Explicit "Advance Day" Action

**File:** `components/game-header.tsx` or dashboard

```tsx
<Button 
  onClick={advanceDay}
  disabled={isProcessing}
  className="bg-amber-600"
>
  <Calendar />
  Advance to Day {gameState.day + 1}
</Button>
```

### 7. Improve Empty State Messaging

**File:** `pages/game/dashboard.tsx` (LocationCard)

```tsx
{inventory.length === 0 && (
  <div className="text-amber-600">
    <Package /> No stock - Needs Restock
  </div>
)}
```

---

## State Machine Diagrams

### Order State Machine

```
Draft → Pending → Shipped → Delivered
                     ↓
                 Cancelled
```

**State Transition Rules:**

| From | To | Conditions | Effect |
|-------|-----|------------|--------|
| Draft | Pending | User finalizes order | Cash deducted, `OrderPlaced` event fired |
| Pending | Shipped | Order ships (route dependent) | `delivery_day` calculated based on transit days |
| Shipped | Delivered | Transit days elapsed | Inventory updated, `OrderDelivered` event |
| Shipped | Cancelled | User cancels shipped order | Full refund, status = cancelled |
| Delivered | Cancelled | User attempts cancellation | **BLOCKED** - Validation error |

### Route State Machine

```
Active → Blocked (Spike) → Restored (Spike Ends)
         ↓
    Unavailable (Capacity Full)
```

**State Transition Rules:**

| Event | Route State Change | UI Impact |
|-------|------------------|------------|
| Spike activates | Active → Blocked | Route picker shows disabled state, "Blocked" badge |
| Spike ends | Blocked → Active | Route becomes selectable again |
| Order exceeds capacity | Active → Unavailable | Capacity meter shows full, validation error |
| Order placed (partial capacity) | Active → Reduced Capacity | Capacity meter shows remaining |

---

## User Journey Flowchart

### Complete 5-Day Player Journey

```
┌─────────────────────────────────────────────────────────────┐
│ DAY 1: START                                         │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Dashboard: Shows $1M cash, empty inventory      │  │
│ │ Decision: Need to stock all locations           │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Action: Place Initial Order                      │  │
│ │ - Select: Standard Truck Route                   │  │
│ │ - Items: 100 units Premium Arabica             │  │
│ │ - Cost: $10,000                            │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ Cash: $990,000                                    │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ DAY 2: DISRUPTION                                   │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Event: Blizzard Activates                        │  │
│ │ - Standard Route: BLOCKED                       │  │
│ │ - Dashboard: Spike alert banner                 │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Decision Point: Emergency Response                 │  │
│ │ Option A: Wait (Day 5 delivery)              │  │
│ │ Option B: Premium Air Route                    │  │
│ │           ↓ (Player chooses this)               │  │
│ │ - Transit: 1 day                             │  │
│ │ - Cost: $20,000                             │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ Cash: $970,000                                    │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ DAY 3: SHIPMENT                                      │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Status Update: Both Orders Ship                  │  │
│ │ - Standard Order: Day 3 → Day 5 delivery       │  │
│ │ - Emergency Order: Day 3 → Day 4 delivery       │  │
│ └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ DAY 4: RESTORATION                                   │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Event: Blizzard Ends                             │  │
│ │ - Standard Route: RESTORED                      │  │
│ │ - Dashboard: Alert banner disappears             │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Delivery: Emergency Order Arrives                 │  │
│ │ - Inventory: +50 units                       │  │
│ │ - Order Status: Delivered                     │  │
│ └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ DAY 5: FINAL DELIVERY                                │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Delivery: Standard Order Arrives                  │  │
│ │ - Inventory: +100 units                       │  │
│ │ - Order Status: Delivered                     │  │
│ └───────────────┬───────────────────────────────┘  │
│                 ↓                                     │
│ ┌───────────────────────────────────────────────────────┐  │
│ │ Verification:                                   │  │
│ │ - Total Inventory: 150 units                   │  │
│ │ - Final Cash: $968,000                       │  │
│ └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## API Contracts for Missing Routes

### GET /game/logistics/routes

**Purpose:** Retrieve available delivery routes between source and target locations

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|-------|-----------|-------------|
| source_id | string | Yes | Source location UUID |
| target_id | string | Yes | Target location UUID |

**Response (200 OK):**
```json
{
  "success": true,
  "routes": [
    {
      "id": 1,
      "name": "Standard Truck Route",
      "source_location_id": "uuid-1",
      "target_location_id": "uuid-2",
      "transport_mode": "Truck",
      "cost": 1000,
      "transit_days": 2,
      "capacity": 1000,
      "weather_vulnerability": true,
      "is_active": true,
      "is_premium": false,
      "blocked_reason": null
    },
    {
      "id": 2,
      "name": "Premium Air Route",
      "source_location_id": "uuid-1",
      "target_location_id": "uuid-2",
      "transport_mode": "Air",
      "cost": 5000,
      "transit_days": 1,
      "capacity": 500,
      "weather_vulnerability": false,
      "is_active": true,
      "is_premium": true,
      "blocked_reason": null
    }
  ]
}
```

**Response (422 - Blocked Routes Only):**
```json
{
  "success": false,
  "message": "All routes to target location are currently blocked",
  "routes": []
}
```

### POST /game/orders/{id}/cancel

**Purpose:** Cancel a shipped order and trigger refund

**Path Parameters:**
| Parameter | Type | Description |
|-----------|-------|-------------|
| id | UUID | Order identifier |

**Validation Rules:**
```php
// Server-side validation
$order->status !== 'delivered' // Cannot cancel delivered orders
$order->status === 'shipped'   // Can only cancel shipped orders
Auth::id() === $order->user_id   // User must own order
```

**Response (200 OK - Successful Cancellation):**
```json
{
  "success": true,
  "message": "Order cancelled and refunded",
  "order": {
    "id": "order-uuid",
    "status": "cancelled",
    "refund_amount": 5000,
    "refunded_at": "2026-01-17T10:30:00Z"
  },
  "cash_balance": 973000
}
```

**Response (422 - Validation Error):**
```json
{
  "message": "Cannot cancel this order",
  "errors": {
    "status": ["Delivered orders cannot be cancelled"]
  }
}
```

### GET /game/orders/{id}/capacity-check

**Purpose:** Check if order quantity fits within selected route capacity

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|-------|-----------|-------------|
| route_id | integer | Yes | Selected route ID |

**Response (200 OK):**
```json
{
  "within_capacity": false,
  "order_quantity": 1200,
  "route_capacity": 1000,
  "excess": 200,
  "suggestion": "Reduce order by 200 units or choose premium route with higher capacity"
}
```

---

## Acceptance Criteria Checklist

### Route Selection Feature

**Component:** `RoutePicker` in `/game/ordering.tsx`

- [ ] Route picker component renders on order creation page
- [ ] Displays all available routes between source and target locations
- [ ] Each route shows: name, transport mode, cost, transit days, capacity
- [ ] Weather vulnerability indicator displayed for each route
- [ ] Blocked routes show as disabled with "Blocked" badge and reason
- [ ] Premium routes highlighted with cost difference badge (+$X)
- [ ] Route selection updates order total calculation in real-time
- [ ] Capacity meter shows current order quantity vs route limit
- [ ] Validation error shown if order exceeds selected route capacity
- [ ] Route persists when order is saved to database

**Backend:**
- [ ] `GET /game/logistics/routes` endpoint implemented
- [ ] Route filtering by source and target location
- [ ] Returns weather vulnerability status
- [ ] Returns capacity information
- [ ] Returns active/inactive status
- [ ] Premium route flag present in response

### Order Cancellation Feature

**Component:** Order action buttons in `/game/ordering.tsx`

- [ ] Cancel button appears only for "shipped" status orders
- [ ] Cancel button is hidden for "draft", "pending", "delivered" statuses
- [ ] Clicking cancel opens confirmation dialog
- [ ] Confirmation dialog shows refund amount
- [ ] Confirmation dialog warns "Order will be cancelled"
- [ ] After confirmation, order status changes to "cancelled"
- [ ] Success toast displays: "Order cancelled, $X refunded"
- [ ] Orders table refreshes to show updated status
- [ ] Cash balance displayed is updated

**Backend:**
- [ ] `POST /game/orders/{id}/cancel` endpoint implemented
- [ ] Validation prevents cancellation of "delivered" orders
- [ ] Validation allows cancellation of "shipped" orders
- [ ] Refund amount matches original order total
- [ ] Cash balance updated atomically with status change
- [ ] Transaction recorded for audit trail

### Route Capacity Validation Feature

**Component:** `RouteCapacity` display in order/transfer forms

- [ ] Capacity meter shows current usage vs max
- [ ] Visual indicator: green (<80%), amber (80-95%), red (>95%)
- [ ] Animation when capacity changes during item quantity adjustment
- [ ] Error message appears when capacity exceeded
- [ ] Error message shows excess amount
- [ ] Submit button disabled when capacity exceeded
- [ ] Suggestion displayed: "Reduce by X units or choose different route"

**Backend:**
- [ ] Capacity check performed before order creation
- [ ] Returns 422 if capacity exceeded
- [ ] Error message includes excess amount
- [ ] Capacity validated against route current load (not just base capacity)
- [ ] Premium routes have different capacities respected

### Day Counter Feature

**Component:** `DayCounter` in dashboard or header

- [ ] Displays "Day X of Y" prominently
- [ ] Current day highlighted or emphasized
- [ ] Total days configurable (default: 5)
- [ ] Updates automatically when day advances
- [ ] Animation on day change (fade/slide)
- [ ] Screen reader announces "Now on day X"
- [ ] Keyboard accessible (Tab navigation)

**Backend:**
- [ ] `gameState.day` persisted correctly
- [ ] Day advances only via authorized action
- [ ] Maximum day constraint enforced (cannot exceed Y days)

### Cash Display Feature

**Component:** `CashDisplay` in dashboard or header

- [ ] Shows current cash balance prominently
- [ ] Currency formatted with commas ($X,XXX,XXX)
- [ ] Updates in real-time when orders placed/cancelled
- [ ] Color-coded: green (increased), red (decreased), neutral (stable)
- [ ] Brief animation on value change
- [ ] Tooltip on hover shows transaction history summary

---

## Error Handling Documentation

### Cancel Delivered Order

**Scenario:** User attempts to cancel an order that has already been delivered

**User Action:**
1. User navigates to `/game/ordering`
2. User locates "Delivered" order
3. User attempts to click cancel (should not exist) or calls API directly

**Expected Error:** "Cannot cancel delivered orders"

**UI Behavior:**
- Cancel button is **disabled** or **hidden** for delivered orders
- If API called directly, shows inline error: `This order has already been delivered and cannot be cancelled`
- Error style: Red alert box with exclamation icon
- Error persists until user dismisses or navigates away

**Backend Response:**
```json
{
  "message": "Cannot cancel this order",
  "errors": {
    "status": ["Delivered orders cannot be cancelled. Only shipped orders can be cancelled for refunds."]
  }
}
```

**Test Coverage:**
```php
// GameController.php: cannotCancelDeliveredOrder()
try {
    $deliveredOrder->status->transitionTo(Cancelled::class);
    $this->fail("Should not be able to cancel delivered order");
} catch (\Throwable $e) {
    $this->assertStringContainsString('cannot be cancelled', $e->getMessage());
}
```

### Exceed Route Capacity

**Scenario:** User places order quantity that exceeds selected route capacity

**User Action:**
1. User selects route with capacity of 1,000 units
2. User adds items totaling 1,200 units
3. User attempts to submit order

**Expected Error:** "Order exceeds route capacity by 200 units"

**UI Behavior:**
- Capacity meter shows full with red color
- Inline error appears under route selection: `Order exceeds route capacity by 200 units`
- Submit button **disabled** with tooltip: "Reduce order quantity to continue"
- Suggestion link appears: "View premium route with higher capacity"
- Error persists until quantity reduced or route changed

**Backend Response:**
```json
{
  "message": "Order validation failed",
  "errors": {
    "capacity": ["Order quantity (1,200) exceeds route capacity (1,000). Reduce by 200 units."]
  }
}
```

**Test Coverage:**
```php
// GameController.php: orderExceedsRouteCapacity()
$massiveOrder = Order::create([
    'items' => [['quantity' => $route->capacity + 1]]
]);

try {
    $massiveOrder->status->transitionTo(Pending::class);
    $this->fail("Should not be able to ship order exceeding route capacity");
} catch (\RuntimeException $e) {
    $this->assertStringContainsString('exceeds route capacity', $e->getMessage());
}
```

### Order with Blocked Route

**Scenario:** User attempts to place order via route blocked by active spike

**User Action:**
1. Active spike (blizzard) blocking standard truck route
2. User selects standard truck route in order form
3. User attempts to submit order

**Expected Error:** "Selected route is currently blocked by blizzard"

**UI Behavior:**
- Route picker shows blocked routes as **disabled** with gray opacity
- Blocked routes display "⚠️ Blocked: Blizzard" badge
- If user somehow selects blocked route, shows error: `This route is unavailable due to active weather event`
- Suggestion appears: "Premium Air route is available (+$4,000)"
- Submit button disabled when blocked route selected

**Backend Response:**
```json
{
  "message": "Route unavailable",
  "errors": {
    "route_id": ["Route is currently blocked by active blizzard event. Expected to restore on Day 4."]
  }
}
```

### Insufficient Cash for Order

**Scenario:** User attempts to place order with insufficient cash balance

**User Action:**
1. Cash balance: $5,000
2. Order total: $10,000
3. User attempts to submit order

**Expected Error:** "Insufficient funds for this order"

**UI Behavior:**
- Cash display shows current balance prominently
- Order total displays in red when exceeds cash
- Error message: `You need $5,000 more to place this order`
- Suggestion: "Sell excess inventory or cancel pending orders"
- Submit button disabled

**Backend Response:**
```json
{
  "message": "Insufficient funds",
  "errors": {
    "total_cost": ["Order total ($10,000) exceeds available cash ($5,000)."]
  }
}
```

---

## Testing Verification Checklist

### Day 1 - Initial Order Flow

**Prerequisites:**
- [ ] Fresh game state (Day 1, $1,000,000 cash)
- [ ] No active spike events
- [ ] All locations have empty inventory

**Manual Test Steps:**
- [ ] Navigate to `/game/dashboard`
- [ ] Verify: Dashboard displays "Day 1 of 5" counter
- [ ] Verify: Cash display shows "$1,000,000"
- [ ] Verify: All location cards show critical/empty state
- [ ] Click "Restock" quick action button
- [ ] Verify: Redirects to `/game/ordering`
- [ ] Verify: Route picker displays Standard Truck and Premium Air options
- [ ] Verify: Standard Truck shows cost: $1,000, transit: 2 days
- [ ] Verify: Premium Air shows cost: $5,000, transit: 1 day
- [ ] Select "Premium Arabica" product
- [ ] Enter quantity: 100
- [ ] Select Standard Truck route
- [ ] Verify: Order total displays $10,000
- [ ] Verify: Submit button is enabled
- [ ] Click "Place Order" button
- [ ] Verify: Success toast displays "Order placed successfully"
- [ ] Verify: Redirect to orders list
- [ ] Verify: New order appears with status "Pending"
- [ ] Verify: Dashboard cash display updates to $990,000
- [ ] Verify: Cash briefly animates from green to red

**Expected Result:**
- Order created with Standard Truck route
- Cash decreased by $10,000
- Day remains at 1

---

### Day 2 - Blizzard Disruption Flow

**Prerequisites:**
- [ ] One pending order in system
- [ ] Advance to Day 2 (trigger blizzard event)

**Manual Test Steps:**
- [ ] Navigate to `/game/dashboard`
- [ ] Verify: Red spike alert banner appears at top
- [ ] Verify: Banner displays "Active Spike: Blizzard"
- [ ] Verify: Banner shows "Day 2 - Day 4" duration
- [ ] Click "View Details" button in banner
- [ ] Verify: Redirects to `/game/spike-history`
- [ ] Navigate to `/game/transfers`
- [ ] Verify: Route picker shows "Standard Truck" as disabled
- [ ] Verify: "Standard Truck" displays "⚠️ Blocked" badge
- [ ] Verify: "Blocked reason: Blizzard" text appears
- [ ] Verify: "Premium Air" route still available
- [ ] Select source: Global Beans HQ
- [ ] Select target: Central Warehouse
- [ ] Verify: Path finding shows "All Routes Blocked" message
- [ ] Verify: No "Direct Route Active" message

**Expected Result:**
- Spike event visible on dashboard
- Standard route blocked in route picker
- Premium route remains available

---

### Day 2 - Emergency Order Flow

**Prerequisites:**
- [ ] Active blizzard blocking standard route
- [ ] Day 2 displayed on dashboard

**Manual Test Steps:**
- [ ] Navigate to `/game/ordering`
- [ ] Click "New Order" button
- [ ] Select: Premium Air route
- [ ] Verify: Premium route shows "Air" transport mode
- [ ] Verify: Cost displays as $5,000 (premium pricing)
- [ ] Verify: Transit days: 1 day
- [ ] Select: "Premium Arabica" product
- [ ] Enter quantity: 50
- [ ] Verify: Order total displays $20,000 ($25/unit × 50 + $5,000 shipping)
- [ ] Click "Place Order" button
- [ ] Verify: Success toast displays
- [ ] Navigate to `/game/dashboard`
- [ ] Verify: Cash display updates to $970,000
- [ ] Verify: Cash animation shows red (decrease)
- [ ] Navigate to `/game/ordering`
- [ ] Verify: Two orders now listed
- [ ] Verify: Emergency order shows "Pending" status
- [ ] Verify: Delivery day shows "Day 4" (Day 2 + 1 day transit + 1 day to ship)

**Expected Result:**
- Emergency order created via Premium Air route
- Cash decreased by $20,000
- Delivery day calculated correctly

---

### Day 4 - Delivery and Restoration Flow

**Prerequisites:**
- [ ] Emergency order shipped on Day 3
- [ ] Active blizzard event
- [ ] Advance to Day 4

**Manual Test Steps:**
- [ ] Navigate to `/game/dashboard`
- [ ] Verify: Spike alert banner has **disappeared**
- [ ] Navigate to `/game/transfers`
- [ ] Verify: Standard Truck route shows as **enabled**
- [ ] Verify: "Blocked" badge is removed
- [ ] Verify: "Route Active" message displayed
- [ ] Navigate to `/game/ordering`
- [ ] Verify: Emergency order status shows "Delivered"
- [ ] Verify: Delivery day shows "Day 4"
- [ ] Verify: Standard order status still shows "Shipped"
- [ ] Navigate to `/game/inventory`
- [ ] Select: Central Warehouse location
- [ ] Verify: Premium Arabica quantity: 50 units
- [ ] Verify: No delivery on standard order yet

**Expected Result:**
- Blizzard ended, banner removed
- Standard route restored
- Emergency order delivered
- Inventory updated

---

### Order Cancellation Flow

**Prerequisites:**
- [ ] At least one order in "shipped" status
- [ ] At least one order in "delivered" status

**Manual Test Steps - Cancel Shipped Order:**
- [ ] Navigate to `/game/ordering`
- [ ] Locate order with "Shipped" status
- [ ] Verify: Cancel button is **visible**
- [ ] Click cancel button
- [ ] Verify: Confirmation dialog appears
- [ ] Verify: Dialog shows "Cancel & Refund" heading
- [ ] Verify: Dialog shows refund amount (e.g., $5,000)
- [ ] Click "Confirm Cancellation" button
- [ ] Verify: Success toast: "Order cancelled, $5,000 refunded"
- [ ] Verify: Order status changes to "Cancelled"
- [ ] Navigate to `/game/dashboard`
- [ ] Verify: Cash display has increased by refund amount
- [ ] Verify: Cash animation shows green (increase)

**Manual Test Steps - Cannot Cancel Delivered Order:**
- [ ] Locate order with "Delivered" status
- [ ] Verify: Cancel button is **hidden** or **disabled**
- [ ] If disabled, hover to verify tooltip: "Delivered orders cannot be cancelled"
- [ ] (Optional) Attempt API call directly to cancel delivered order
- [ ] Verify: Returns 422 error
- [ ] Verify: Error message: "Delivered orders cannot be cancelled"

**Expected Result:**
- Shipped orders can be cancelled with refund
- Delivered orders cannot be cancelled
- Cash balance reflects refunds correctly

---

### Route Capacity Validation Flow

**Prerequisites:**
- [ ] Route with capacity of 1,000 units available
- [ ] Order form open with route selected

**Manual Test Steps - Valid Capacity:**
- [ ] Select: Standard Truck route (capacity: 1,000)
- [ ] Add items totaling 800 units
- [ ] Verify: Capacity meter shows green (<80%)
- [ ] Verify: Capacity text: "800 / 1,000 units"
- [ ] Verify: Submit button is **enabled**
- [ ] Submit order
- [ ] Verify: Order created successfully

**Manual Test Steps - Exceeded Capacity:**
- [ ] Add additional items to reach 1,200 units total
- [ ] Verify: Capacity meter shows red (>100%)
- [ ] Verify: Capacity text: "1,200 / 1,000 units (exceeded by 200)"
- [ ] Verify: Inline error appears: "Order exceeds route capacity by 200 units"
- [ ] Verify: Submit button is **disabled**
- [ ] Verify: Submit button shows tooltip: "Reduce order quantity to continue"
- [ ] Verify: Suggestion appears: "View premium route with higher capacity"
- [ ] Reduce quantity to 1,000 units
- [ ] Verify: Error message disappears
- [ ] Verify: Capacity meter changes to green
- [ ] Verify: Submit button becomes enabled

**Expected Result:**
- Capacity validation prevents invalid orders
- UI provides clear feedback and guidance
- User can correct issue easily

---

## Visual Mockups

### Route Picker Component

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Select Delivery Route                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                │
│ ◉ Standard Truck Route              [Recommended]                  │
│    ┌──────────────────────────────────────────────────────────────┐  │
│    │ Transport Mode: 🚚 Truck                               │  │
│    │ Cost: $1,000                                         │  │
│    │ Transit Time: 2 days                                    │  │
│    │ Capacity: 1,000 units                                  │  │
│    │ ┌────────────────────────────────────────────────────────┐  │  │
│    │ │ 0%███░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░│  │  │
│    │ └────────────────────────────────────────────────────────┘  │  │
│    │ ⚠️ Weather Vulnerable (May be blocked during storms)     │  │
│    └──────────────────────────────────────────────────────────────┘  │
│                                                                │
│ ○ Premium Air Route                                            │
│    ┌──────────────────────────────────────────────────────────────┐  │
│    │ Transport Mode: ✈️ Air                                 │  │
│    │ Cost: $5,000  [+$4,000 vs Standard]                   │  │
│    │ Transit Time: 1 day [-1 day vs Standard]                │  │
│    │ Capacity: 500 units                                    │  │
│    │ ┌────────────────────────────────────────────────────────┐  │  │
│    │ │ 0%███░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░│  │  │
│    │ └────────────────────────────────────────────────────────┘  │  │
│    │ ✓ Weather Protected (Never blocked by storms)            │  │
│    └──────────────────────────────────────────────────────────────┘  │
│                                                                │
│ Current Order: 100 units of Premium Arabica                       │
│                                                                │
├─────────────────────────────────────────────────────────────────────────┤
│ Order Total: $10,000      [Place Order]                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Blocked Route State (During Blizzard)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Select Delivery Route                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                │
│ ● Standard Truck Route              [BLOCKED]                      │
│    ┌──────────────────────────────────────────────────────────────┐  │
│    │ Transport Mode: 🚚 Truck                               │  │
│    │ Cost: $1,000                                         │  │
│    │ Transit Time: 2 days                                    │  │
│    │ ⛔ BLOCKED: Blizzard (Day 2 - Day 4)                   │  │
│    │ ❌ Route unavailable due to severe weather                 │  │
│    └──────────────────────────────────────────────────────────────┘  │
│                                                                │
│ ◉ Premium Air Route              [Only Option Available]             │
│    ┌──────────────────────────────────────────────────────────────┐  │
│    │ Transport Mode: ✈️ Air                                 │  │
│    │ Cost: $5,000                                         │  │
│    │ Transit Time: 1 day                                    │  │
│    │ ✓ Available (Weather Protected)                            │  │
│    └──────────────────────────────────────────────────────────────┘  │
│                                                                │
│ ⚠️ Your standard route is blocked. Premium route required.          │
│                                                                │
├─────────────────────────────────────────────────────────────────────────┤
│ Order Total: $20,000      [Place Order]                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Order Table with Cancellation Button

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Recent Orders                                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                │
│ ┌────────────────────────────────────────────────────────────────────┐  │
│ │ Order ID │ Vendor    │ Items  │ Total    │ Status     │ Action │  │
│ ├────────────────────────────────────────────────────────────────────┤  │
│ │ abc12345 │ Global     │ 100     │ $10,000   │ Pending   │  │
│ │          │ Beans      │ units   │           │ [Place]   │  │
│ ├────────────────────────────────────────────────────────────────────┤  │
│ │ def67890 │ Global     │ 50      │ $20,000   │ Shipped   │  │
│ │          │ Beans      │ units   │           │ Day 4     │ [Cancel  │  │
│ │          │            │         │           │           │ & Refund]│  │
│ ├────────────────────────────────────────────────────────────────────┤  │
│ │ ghi01234 │ Global     │ 75      │ $7,500    │ Delivered │  │
│ │          │ Beans      │ units   │           │ Day 5     │ N/A      │  │
│ └────────────────────────────────────────────────────────────────────┘  │
│                                                                │
└─────────────────────────────────────────────────────────────────────────┘
```

### Dashboard Header with Day Counter and Cash

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Moonshine Coffee Management Sim              [☰] [🔔] [👤]     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                │
│ ┌──────────────────────────────────────────────────────────────────┐   │
│ │ [📅] Day 1 of 5                                       │   │
│ │ [💰] $1,000,000                                      │   │
│ │ [➡️] Advance to Day 2                                 │   │
│ └──────────────────────────────────────────────────────────────────┘   │
│                                                                │
└─────────────────────────────────────────────────────────────────────────┘
```

### Capacity Exceeded Error State

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Select Delivery Route                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                │
│ ◉ Standard Truck Route                                          │
│    ┌──────────────────────────────────────────────────────────────┐  │
│    │ Cost: $1,000  │ Transit: 2 days                      │  │
│    │                                                        │  │
│    │ Capacity:                                                 │  │
│    │ ┌────────────────────────────────────────────────────────┐    │  │
│    │ │█████████████████████████████████████████████████░░│ 100%  │  │
│    │ └────────────────────────────────────────────────────────┘ 1200/│  │
│    │                                                        │1000   │  │
│    │ ❌ Error: Order exceeds route capacity by 200 units        │  │
│    │                                                        │  │
│    │ 💡 Suggestion: Reduce quantity by 200 units or choose      │  │
│    │    Premium Air route (capacity: 500, still insufficient)    │  │
│    └──────────────────────────────────────────────────────────────┘  │
│                                                                │
├─────────────────────────────────────────────────────────────────────────┤
│ [Place Order] [Disabled - Exceeds capacity]                      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Performance & Real-time Updates

### Day Advancement Flow

**User Action:** Click "Advance to Day X" button

**Sequence:**
```
1. UI State Change
   └─ Button shows loading spinner
   └─ Button text: "Advancing..."
   └─ Button disabled during request

2. API Request
   └─ POST /game/advance-day
   └─ Request includes: current_day, user_id
   └─ Response: new_day, updated_cash, new_events

3. Processing Animation (100-300ms)
   └─ Day counter fades out (opacity: 1 → 0)
   └─ Counter increments: Day 1 → Day 2
   └─ Counter fades in (opacity: 0 → 1)
   └─ Animation: slide-up or pulse effect

4. Success Feedback
   └─ Toast notification: "Advanced to Day 2"
   └─ Toast persists for 3 seconds
   └─ Cash display: animation (green arrow up or red arrow down)

5. Data Refresh
   └─ Inertia partial reload: only(['game'])
   └─ New events load (spikes, route changes)
   └─ Location cards update status
   └─ Alerts list refreshes

6. Spike Detection (if applicable)
   └─ If spike event starts:
      └─ Immediate notification toast: "⚠️ Blizzard detected!"
      └─ Red banner slides in from top
      └─ Location card animates with warning border
      └─ Route pickers update to show blocked status
```

**Performance Requirements:**
- Day advance API response: < 500ms
- Animation duration: 300ms (not blocking)
- Data refresh via Inertia partial reload (not full page)
- Total time from click to ready state: < 1 second

### Spike Activation Flow

**Trigger:** SimulationService detects spike event

**Sequence:**
```
1. Server-Side Detection
   └─ SimulationService::advanceTime() runs
   └─ SpikeEvent created with is_active = true
   └─ Route::update(['is_active' => false])

2. Real-Time Push (Optional - if using WebSockets)
   └─ Channel: user.{user_id}
   └─ Event: SpikeActivated { type, magnitude, duration }
   └─ Subscribed clients receive immediately

3. Inertia Props Update (Current implementation)
   └─ Next page refresh includes new spike data
   └─ Dashboard banner conditionally renders based on currentSpike

4. UI Updates
   └─ Red banner slides in from top (height: 0 → 80px)
   └─ Banner shows spike icon + name + duration
   └─ Route pickers: Standard route becomes disabled
   └─ Route pickers: Premium route becomes "Only Option"
   └─ Location cards: Affected location shows red pulse animation
   └─ KPIs: "Active Spikes" count increments
   └─ Quests: Any spike-related quests may complete

5. User Notification
   └─ Toast appears: "⚠️ Blizzard has blocked standard routes"
   └─ Toast persists for 5 seconds (longer than usual)
   └─ Sound effect: alert tone (optional, user-configurable)

6. Suggested Actions Update
   └─ LogisticsStatusWidget recalculates health score
   └─ Suggestions array updated with emergency options
   └─ Dashboard shows new suggested action cards
```

### Order Status Transition Flow

**Trigger:** Backend order state machine updates

**Sequence:**
```
1. State Change Detected (Polling or WebSockets)
   └─ Order status: Pending → Shipped
   └─ Delivery day calculated: current_day + transit_days

2. Orders Table Update
   └─ Specific order row updates (not full table refresh)
   └─ Status badge: Pending → Shipped (blue badge)
   └─ Delivery day column populated: "Day 5"
   └─ Row highlights briefly (yellow flash) to indicate change

3. Dashboard KPI Update
   └─ "Pending Orders" count decreases
   └─ "In Transit" count increases
   └─ Numbers animate (count-up or count-down)

4. Notification
   └─ Toast: "Order #abc12345 has shipped"
   └─ Toast shows order ID and new status
   └─ Toast persists for 3 seconds

5. Inventory Preview (if applicable)
   └─ If inventory page open, update estimated arrival
   └─ Show "ETA: Day X" badge on product card
```

### Cash Flow Animation

**Trigger:** Cash balance changes (order, cancellation, refund)

**Sequence:**
```
1. Value Comparison
   └─ Previous: $1,000,000
   └─ Current: $990,000
   └─ Difference: -$10,000 (decrease)

2. Animation Types
   └─ Decrease (-$):
      ├─ Red color briefly (#ef4444)
      ├─ Down arrow icon (↓)
      └─ Shrink effect (scale: 1.1 → 1.0)
   └─ Increase (+$):
      ├─ Green color briefly (#22c55e)
      ├─ Up arrow icon (↑)
      └─ Grow effect (scale: 1.1 → 1.0)

3. Counter Animation
   └─ Numbers count up/down (not instant jump)
   └─ Duration: 500ms
   └─ Easing: ease-out
   └─ Commas format preserved: $1,000,000 → $999,999 → $990,000

4. Accessibility
   └─ Screen reader announces: "Cash decreased by $10,000 to $990,000"
   └─ Reduced motion preference disables animation
   └─ High contrast mode removes color flash, keeps icon
```

### Route Capacity Real-time Validation

**Trigger:** User adjusts item quantity in order form

**Sequence:**
```
1. Quantity Change Event
   └─ User increments/decrements item quantity
   └─ Debounce: 300ms to avoid excessive API calls

2. Capacity Calculation
   └─ Total order quantity: sum of all line items
   └─ Compare against selected route capacity
   └─ Calculate: remaining = capacity - total
   └─ Determine: percentage = (total / capacity) * 100

3. Visual Updates
   └─ 0-80%: Green meter, "Safe"
   └─ 80-95%: Amber meter, "Approaching Limit"
   └─ 95-100%: Red meter, "Near Capacity"
   └─ >100%: Red meter with error state, "Exceeded"

4. Form State
   └─ < 100%: Submit button enabled
   └─ = 100%: Submit button enabled (at exact capacity)
   └─ > 100%: Submit button disabled

5. Error Messages
   └─ Exceeded state shows inline error immediately
   └─ Error shows exact excess amount
   └─ Suggestions appear (reduce quantity or change route)
   └─ Errors animate in (slide down from top of form)
```

### Performance Optimization Guidelines

**Caching:**
- Route data: Cache for 5 minutes (routes rarely change)
- Location data: Cache for 1 minute (inventory changes often)
- Spike events: No cache (must be real-time)

**Debouncing:**
- Quantity changes: 300ms debounce before capacity validation
- Search inputs: 200ms debounce
- Filter selections: No debounce (immediate)

**Partial Reloads:**
- Use Inertia `only()` prop to reload only changed data
- Avoid full page refreshes
- Preserve scroll position with `preserveScroll: true`

**Lazy Loading:**
- Order history: Load first 10, lazy load more on scroll
- Transfer history: Same approach
- Spike history: Paginate, don't load all at once

---

## Implementation Priority

1. **Immediate (Required for test validation):**
   - Order cancellation UI
   - Route selection in orders
   - Route capacity validation feedback

2. **High Priority (UX improvement):**
   - Day counter display
   - Cash display prominently
   - Advance Day button visibility

3. **Medium Priority (Onboarding):**
   - Day 1 welcome banner
   - First quest guidance
   - Improved empty states

4. **Low Priority (Polish):**
   - Animated day transitions
   - Cash flow animations
   - Route comparison visualizations

---

## Appendix: Test Scenarios Quick Reference

| Day | Test Action | UI Component Expected | Current Status |
|-----|-------------|---------------------|----------------|
| 1 | Place initial order | `/game/ordering` with route picker | ⚠️ Missing route picker |
| 2 | Blizzard starts | Dashboard spike alert banner | ✅ Implemented |
| 2 | Route blocks | Transfers page shows blocked | ✅ Implemented |
| 2 | Emergency order | `/game/ordering` with premium route option | ❌ Missing |
| 3 | Orders ship | Orders table shows "Shipped" status | ✅ Implemented |
| 4 | Blizzard ends | Alert banner disappears | ✅ Implemented |
| 4 | Emergency delivery | Order shows "Delivered" | ✅ Implemented |
| 5 | Standard delivery | Order shows "Delivered" | ✅ Implemented |
| 5 | Inventory check | `/game/inventory` shows quantities | ✅ Implemented |
| - | Cancel delivered order | Cannot cancel (validation) | ❌ No UI |
| - | Cancel shipped order | Cancel button + refund | ❌ No UI |
| - | Capacity check | Error on excess | ❌ No UI |

---

## Related Files

- **Test:** `tests/Feature/GameplayLoopVerificationTest.php`
- **Dashboard Page:** `resources/js/pages/game/dashboard.tsx`
- **Ordering Page:** `resources/js/pages/game/ordering.tsx`
- **Transfers Page:** `resources/js/pages/game/transfers.tsx`
- **Game Context:** `resources/js/contexts/game-context.tsx`
- **Controller:** `app/Http/Controllers/GameController.php`
- **Services:** 
  - `resources/js/services/cockpitService.ts`
  - `resources/js/services/spikeService.ts`
  - `app/Services/LogisticsService.php`
  - `app/Services/SimulationService.php`
- **Routes:** `routes/web.php`

---
