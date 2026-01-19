# Dashboard UX & Gameplay Loop Test Alignment — Implementation Plan

**Created**: 2026-01-19  
**Completed**: TBD  
**Status**: 🟡 Planned  
**Purpose**: Close the UX + API gaps called out in `docs/dashboard-ux-test-gap-analysis.md` so the React/Inertia dashboard and procurement flows fully support (and can visually validate) the 5‑day gameplay loop mechanics.

---

## Problem Statement
The gameplay loop verification test (`tests/Feature/GameplayLoopVerificationTest.php`) asserts that a player can meaningfully interact with the simulation across 5 days: place orders via different routes, respond to route disruptions (spikes), respect route capacity, cancel eligible orders for refunds, and track cash/day progression.

`docs/dashboard-ux-test-gap-analysis.md` identifies multiple mismatches between what the test simulates and what the current UI + endpoints expose.

1. **Route selection and comparison gaps**: the procurement UI must clearly expose Standard vs Premium routes and their tradeoffs (cost/transit/capacity/vulnerability), including “blocked” states during spikes.
2. **Order cancellation gaps**: the UI must only offer cancellation when it is valid (shipped only), and the backend must return Inertia-compatible responses while refunding cash atomically.
3. **Capacity validation gaps**: the UI needs real-time feedback and the backend must validate capacity (and blocked routes) before creating/advancing an order.
4. **Day/cash feedback gaps**: the day counter and cash display need to meet acceptance criteria (formatting, delta feedback, accessibility, partial reload behavior).
5. **Onboarding and empty-state messaging gaps**: Day 1 should guide the first action, and location cards must not show misleading “Systems Normal” states when inventory is empty.

These gaps reduce player agency and make it difficult to verify the mechanics that the test suite asserts.

---

## Design Decisions (Stakeholder Preferences)

| Decision | Choice |
| :--- | :--- |
| Mutations (advance day, place/cancel order) | Use Inertia `router.post()` / `useForm()` with redirects so the server remains source-of-truth. |
| Dynamic reads (routes/capacity checks) | Use JSON endpoints (`fetch`) to avoid full page reloads and to support debounced validation. |
| Validation strategy | Laravel FormRequests + domain checks (Spatie model states) surfaced as `422` validation errors for Inertia forms. |
| Atomic cash updates | Perform state transitions inside `DB::transaction()`; keep cash changes event-driven but transaction-safe. |
| Partial reloads | Prefer Inertia `only()` refreshes of `game` and the current page props (e.g., `orders`) after mutations. |
| Type safety | Update `resources/js/types/index.d.ts` to reflect actual payloads and avoid runtime “undefined” failures. |
| Accessibility | Add `aria-live` announcements and respect `prefers-reduced-motion` for day/cash animations. |
| Minimal dependencies | Prefer existing UI primitives; only add new packages (e.g., toast) if no equivalent exists. |

---

## Solution Architecture

### Overview
```
Player (React/Inertia UI)
  ├─ GET routes (fetch)
  │     └─ GET /game/logistics/routes  → LogisticsController@getRoutes (JSON)
  │
  ├─ Place order (Inertia)
  │     └─ POST /game/orders → GameController@placeOrder (FormRequest + DB txn)
  │            ├─ validate: route active, capacity, funds
  │            ├─ create: Order + OrderItems
  │            └─ transition: Draft → Pending (events update cash/alerts)
  │
  ├─ Cancel order (Inertia)
  │     └─ POST /game/orders/{order}/cancel → GameController@cancelOrder (DB txn)
  │            ├─ validate: shipped only, not delivered, owner
  │            └─ transition: Shipped → Cancelled (events refund cash)
  │
  └─ Advance day (Inertia)
        └─ POST /game/advance-day → SimulationService::advanceTime()
               ├─ process spikes (block/unblock routes)
               ├─ process deliveries
               └─ emit TimeAdvanced (storage costs, alerts, etc.)

Server returns redirect → Inertia updates page props + shared `game` props → GameContext updates header + pages.
```

---

## Implementation Tasks

### Phase 1: Backend Contracts + Validation 🟡 Planned

#### Task 1.1: Align routes API contract with UX needs 🟡 Planned
**File**: `app/Http/Controllers/LogisticsController.php`

```php
return response()->json([
    'success' => true,
    'routes' => $routes->map(fn (Route $route) => [
        'id' => $route->id,
        'name' => "{$route->transport_mode} Route",
        'source_location_id' => $route->source_id,
        'target_location_id' => $route->target_id,
        'transport_mode' => $route->transport_mode,
        'cost' => $this->logistics->calculateCost($route),
        'transit_days' => $route->transit_days,
        'capacity' => $route->capacity,
        'weather_vulnerability' => $route->weather_vulnerability,
        'is_active' => $route->is_active,
        'is_premium' => $this->logistics->isPremiumRoute($route),
        'blocked_reason' => $blockedReason, // derived from active SpikeEvent (if any)
    ]),
]);
```

**Key Logic/Responsibilities**:
* Return `capacity` and `weather_vulnerability` (required by existing TS types and capacity meter).
* Include `is_premium` and `blocked_reason` to support route comparison + disruption UX.
* Provide a consistent response envelope (`success`, `routes`) and a clear error when “all routes blocked”.

---

#### Task 1.2: Add pre-submit server validation for route state, capacity, and funds 🟡 Planned
**File**: `app/Http/Requests/StoreOrderRequest.php`

```php
public function rules(): array
{
    return [
        'vendor_id' => ['required', 'exists:vendors,id'],
        'location_id' => ['required', 'exists:locations,id'],
        'route_id' => ['required', 'exists:routes,id'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'exists:products,id'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
        'items.*.unit_price' => ['required', 'numeric', 'min:0'],
    ];
}
```

**Key Logic/Responsibilities**:
* Validate `route_id` is active (not blocked) at time of submission.
* Validate `sum(items.quantity)` fits available route capacity (base capacity and, if implemented, “current load”).
* Validate `game_states.cash` covers the computed order total (including shipping policy).
* Return `422` with field-level errors (works naturally with Inertia `useForm`).

---

#### Task 1.3: Make cancel endpoint Inertia-compatible and enforce “shipped only” 🟡 Planned
**File**: `app/Http/Controllers/GameController.php`

```php
DB::transaction(function () use ($order) {
    $order->status->transitionTo(\App\States\Order\Cancelled::class);
});

return back()->with('success', 'Order cancelled and refunded.');
```

**Key Logic/Responsibilities**:
* Allow cancellation only when the order is `Shipped` (and owned by the current user).
* Reject `Delivered` cancellations with `422` validation errors (not `500`).
* Keep the cash refund and status change atomic (single transaction).
* Optionally support JSON responses when `request()->expectsJson()` for future non-Inertia consumers.

---

#### Task 1.4: Add optional capacity-check endpoint for debounced UI validation 🟡 Planned
**File**: `routes/web.php`

```php
Route::get('/orders/{order}/capacity-check', [GameController::class, 'capacityCheck'])
    ->name('game.orders.capacity-check');
```

**Key Logic/Responsibilities**:
* Return `{ within_capacity, order_quantity, route_capacity, excess, suggestion }`.
* Use when route capacity depends on dynamic “current load” rather than only static capacity.

---

#### Task 1.5: Expand ordering props with route details 🟡 Planned
**File**: `app/Http/Controllers/GameController.php`

**Key Logic/Responsibilities**:
* Eager-load `route` on orders (`Order::with(['vendor', 'items.product', 'route'])`) so the UI can show “Truck vs Air” and premium indicators.
* Ensure serialization includes the fields the UI needs (transport mode, transit days, cost, capacity).

---

### Phase 2: Procurement UX Completion 🟡 Planned

#### Task 2.1: Update RoutePicker to display full route comparison + blocked reasons 🟡 Planned
**File**: `resources/js/components/game/route-picker.tsx`

```tsx
type RoutesResponse = { success: boolean; routes: RouteModel[]; message?: string };
const mode = route.transport_mode.toLowerCase();
```

**Key Logic/Responsibilities**:
* Consume `{ success, routes }` response and display: name, cost, transit days, capacity, vulnerability.
* Render blocked routes as disabled with explicit `blocked_reason`.
* Normalize `transport_mode` casing to avoid “Air vs air” bugs.

---

#### Task 2.2: Make NewOrderDialog show real totals, errors, and capacity feedback 🟡 Planned
**File**: `resources/js/components/game/new-order-dialog.tsx`

```tsx
const itemsSubtotal = data.items.reduce((sum, i) => sum + i.quantity * i.unit_price, 0);
const shippingCost = selectedRoute?.cost ?? 0;
const total = itemsSubtotal + shippingCost;
```

**Key Logic/Responsibilities**:
* Display a running total (items + shipping) and update on route change.
* Show server validation errors from `useForm` (blocked route, insufficient funds, capacity exceeded).
* Optionally debounce calls to `capacity-check` when “current load” is implemented.

---

#### Task 2.3: Enforce cancellation visibility rules and refresh page/game state 🟡 Planned
**File**: `resources/js/pages/game/ordering.tsx`

```tsx
const isCancellable = order.status.toLowerCase() === 'shipped';
```

**Key Logic/Responsibilities**:
* Only show the cancel action for shipped orders.
* After success, ensure both `orders` and shared `game` props refresh (cash updates).

---

#### Task 2.4: Improve CancelOrderDialog error handling 🟡 Planned
**File**: `resources/js/components/game/cancel-order-dialog.tsx`

**Key Logic/Responsibilities**:
* Display `422` errors inline (e.g., “Delivered orders cannot be cancelled”).
* Ensure the dialog closes only on confirmed success.

---

### Phase 3: Day + Cash Feedback (Header UX) 🟡 Planned

#### Task 3.1: Make DayCounter match acceptance criteria 🟡 Planned
**File**: `resources/js/components/game/day-counter.tsx`

**Key Logic/Responsibilities**:
* Default `totalDays` to 5 for the test scenario (or read from a config prop).
* Add an `aria-live="polite"` region to announce day changes.
* Add a subtle transition on day change (respect reduced motion).

---

#### Task 3.2: Make CashDisplay use comma formatting and delta-based feedback 🟡 Planned
**File**: `resources/js/components/game/cash-display.tsx`

**Key Logic/Responsibilities**:
* Show `$1,000,000` formatting prominently (not only in tooltip).
* Animate and color based on delta (increase/decrease), not absolute balance thresholds.
* Optionally show recent cash events (requires a lightweight cash ledger or derived summary).

---

#### Task 3.3: Improve AdvanceDayButton feedback + partial reload 🟡 Planned
**File**: `resources/js/components/game/advance-day-button.tsx`

**Key Logic/Responsibilities**:
* Show a spinner and “Advancing…” label during request.
* On success, reload only `game` (and current page props when needed) to keep the UI snappy.

---

#### Task 3.4: Add flash/toast notifications via shared Inertia props 🟡 Planned
**File**: `app/Http/Middleware/HandleInertiaRequests.php`

```php
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'error' => fn () => $request->session()->get('error'),
],
```

**Key Logic/Responsibilities**:
* Provide a single notification mechanism for “order placed”, “order cancelled/refunded”, and “advanced to day X”.
* Render on the client (banner or toast) using existing UI primitives.

---

### Phase 4: Day 1 Onboarding + Empty-State Accuracy 🟡 Planned

#### Task 4.1: Keep onboarding CTA until the first order exists 🟡 Planned
**File**: `app/Http/Controllers/GameController.php`

**Key Logic/Responsibilities**:
* Add `hasFirstOrder` (or similar) to dashboard props; gate the Day 1 welcome content accordingly.

---

#### Task 4.2: Replace misleading “Systems Normal” with stock-aware status 🟡 Planned
**File**: `resources/js/pages/game/dashboard.tsx`

**Key Logic/Responsibilities**:
* Provide/compute per-location stock summaries (via props or alerts) so “empty inventory” renders as warning/critical.
* Ensure Day 1 makes “Restock now” obvious even if alerts haven’t been generated yet.

---

### Phase 5: Tests + Verification 🟡 Planned

#### Task 5.1: Add feature tests for new/updated endpoints 🟡 Planned
**File**: `tests/Feature/LogisticsRoutesTest.php`

**Key Logic/Responsibilities**:
* Assert `GET /game/logistics/routes` returns `capacity`, `weather_vulnerability`, `is_premium`, and `blocked_reason`.

---

#### Task 5.2: Add feature tests for cancellation rules and refunds 🟡 Planned
**File**: `tests/Feature/OrderCancellationTest.php`

**Key Logic/Responsibilities**:
* Shipped orders can be cancelled and refunded; delivered orders cannot.
* Refund changes cash as expected (and remains consistent with event listeners).

---

#### Task 5.3: Add feature tests for placement validation failures 🟡 Planned
**File**: `tests/Feature/OrderPlacementValidationTest.php`

**Key Logic/Responsibilities**:
* Capacity exceeded returns `422` with an excess message.
* Blocked route returns `422` with a route error.
* Insufficient funds returns `422` with a total/cash error.

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `app/Http/Controllers/LogisticsController.php` | Modify | 🟡 Planned |
| `app/Http/Controllers/GameController.php` | Modify | 🟡 Planned |
| `app/Http/Requests/StoreOrderRequest.php` | Create | 🟡 Planned |
| `routes/web.php` | Modify | 🟡 Planned |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify | 🟡 Planned |
| `resources/js/components/game/route-picker.tsx` | Modify | 🟡 Planned |
| `resources/js/components/game/new-order-dialog.tsx` | Modify | 🟡 Planned |
| `resources/js/pages/game/ordering.tsx` | Modify | 🟡 Planned |
| `resources/js/components/game/cancel-order-dialog.tsx` | Modify | 🟡 Planned |
| `resources/js/components/game/day-counter.tsx` | Modify | 🟡 Planned |
| `resources/js/components/game/cash-display.tsx` | Modify | 🟡 Planned |
| `resources/js/components/game/advance-day-button.tsx` | Modify | 🟡 Planned |
| `resources/js/pages/game/dashboard.tsx` | Modify | 🟡 Planned |
| `resources/js/types/index.d.ts` | Modify | 🟡 Planned |
| `tests/Feature/LogisticsRoutesTest.php` | Create | 🟡 Planned |
| `tests/Feature/OrderCancellationTest.php` | Create | 🟡 Planned |
| `tests/Feature/OrderPlacementValidationTest.php` | Create | 🟡 Planned |

---

## Execution Order
1. **Backend route contract** — update `GET /game/logistics/routes` and align TS types/RoutePicker parsing.
2. **Order validations** — add FormRequest checks for funds/capacity/blocked routes; ensure `422` errors.
3. **Cancellation flow** — enforce shipped-only, return Inertia responses, and ensure atomic refund.
4. **Procurement UI polish** — route comparison, totals, and inline error states in dialogs.
5. **Header feedback** — day/cash formatting, delta animations, advance-day feedback, and flash/toast plumbing.
6. **Dashboard accuracy** — stock-aware empty states and Day 1 guidance gating.
7. **Tests** — add feature tests and run `composer test` + `pnpm types`.

---

## Edge Cases to Handle
1. **All routes blocked**: routes endpoint returns a clear message; UI disables submission and suggests alternatives. 🟡 Planned
2. **Transport mode casing**: normalize via `toLowerCase()` on the frontend; keep canonical values on backend. 🟡 Planned
3. **Exact capacity**: allow when `quantity === capacity`; error only when `>` capacity. 🟡 Planned
4. **Route becomes blocked after selection**: server rejects with `422 route_id` error; UI re-prompts route selection. 🟡 Planned
5. **Insufficient funds**: server rejects with `422` and a helpful message; UI keeps dialog open and highlights totals. 🟡 Planned
6. **Race on cancellation vs delivery**: server-side validation wins; UI shows “cannot cancel delivered order” error. 🟡 Planned

---

## Rollback Plan
1. Restore the previous routes response shape (or temporarily support both `data` and `routes`) if the UI breaks.
2. Revert FormRequest validation hooks if they cause unexpected `422`s and keep only the minimal rules needed for stability.
3. Revert cancellation response handling (Inertia vs JSON) by keeping a backwards-compatible `expectsJson()` branch.
4. If UX changes regress navigation/performance, disable optional enhancements (animations/toasts) and keep core mechanics.

---

## Success Criteria
- [ ] `GET /game/logistics/routes` returns full route metadata required by the UI (capacity, vulnerability, premium flag, blocked reason).
- [ ] Player can place orders end-to-end with explicit route choice and accurate total cost feedback.
- [ ] Cancellation is offered only for shipped orders; cancelling refunds cash and updates both orders list and header cash.
- [ ] Capacity validation works client-side and server-side; server returns `422` with actionable excess messaging.
- [ ] Day counter and cash display meet formatting + accessibility requirements (commas, delta feedback, announcements).
- [ ] Day 1 onboarding clearly funnels the player to their first procurement action.
- [ ] Location cards show stock-aware statuses and avoid misleading “Systems Normal” when inventory is empty.
- [ ] New feature tests are in place and `composer test` passes.

---
