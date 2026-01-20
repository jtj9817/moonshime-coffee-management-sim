# Controllers & Routes

## Overview

The application uses **Inertia.js** to bridge Laravel controllers with React pages. Controllers return Inertia responses with typed props instead of JSON responses. Routes are defined in `routes/web.php` and use **Laravel Wayfinder** for TypeScript route generation.

## Controller Architecture

Controllers follow Laravel's resourceful controller pattern and are kept thin, delegating business logic to service classes. All game-related controllers require authentication.

## Core Controllers

### WelcomeController

**File**: `app/Http/Controllers/WelcomeController.php`

Handles the application landing page.

**Methods**:

```php
public function __invoke(Request $request): Response
```

**Route**:
```php
GET / → WelcomeController
```

**Returns**: Inertia page `game/welcome` with game state

---

### GameController

**File**: `app/Http/Controllers/GameController.php`

Main controller for game actions and state management.

**Methods**:

#### `index()`
Displays the main game dashboard.

```php
public function index(Request $request): Response
```

**Route**: `GET /game`

**Returns**: Inertia page `game/inventory` with:
- `gameState` - Current game state
- `locations` - All locations with inventory
- `products` - All products
- `vendors` - All vendors with pricing
- `routes` - All shipping routes
- `orders` - User's orders
- `transfers` - User's transfers
- `alerts` - Active alerts
- `spikeEvents` - Active spike events

#### `advanceTime()`
Advances game time by one day.

```php
public function advanceTime(Request $request): RedirectResponse
```

**Route**: `POST /game/advance-time`

**Process**:
1. Validates user has active game state
2. Calls `SimulationService@advanceDay()`
3. Dispatches `TimeAdvanced` event
4. Redirects back with flash message

#### `initialize()`
Initializes a new game for the user.

```php
public function initialize(Request $request): RedirectResponse
```

**Route**: `POST /game/initialize`

**Process**:
1. Calls `InitializeNewGame` action
2. Seeds locations, products, vendors
3. Creates initial inventory
4. Creates game state with starting cash
5. Redirects to game dashboard

#### `orders()` / `storeOrder()`
View orders and create new orders.

```php
public function orders(Request $request): Response
public function storeOrder(StoreOrderRequest $request): RedirectResponse
```

**Routes**:
- `GET /game/orders` - List orders
- `POST /game/orders` - Create order

**Validation** (`StoreOrderRequest`):
```php
[
    'location_id' => 'required|exists:locations,id',
    'vendor_id' => 'required|exists:vendors,id',
    'items' => 'required|array|min:1',
    'items.*.product_id' => 'required|exists:products,id',
    'items.*.quantity' => 'required|integer|min:1',
    'path' => 'nullable|array', // For multi-hop routing
]
```

**Process**:
1. Validates request
2. Calls `OrderService@createOrder()`
3. Deducts cash from game state
4. Creates order with shipments
5. Dispatches `OrderPlaced` event
6. Redirects with success message

#### `cancelOrder()`
Cancels a pending order.

```php
public function cancelOrder(Request $request, Order $order): RedirectResponse
```

**Route**: `POST /game/orders/{order}/cancel`

**Process**:
1. Authorizes user can cancel order
2. Validates order can be cancelled (Draft or Pending status)
3. Refunds cash
4. Updates order status
5. Dispatches `OrderCancelled` event

#### `transfers()` / `storeTransfer()`
View and create internal transfers.

```php
public function transfers(Request $request): Response
public function storeTransfer(Request $request): RedirectResponse
```

**Routes**:
- `GET /game/transfers` - List transfers
- `POST /game/transfers` - Create transfer

#### `policy()`
View and update inventory policies.

```php
public function policy(Request $request): Response
public function updatePolicy(Request $request): RedirectResponse
```

**Routes**:
- `GET /game/policy` - View policies
- `POST /game/policy` - Update policies

#### `alerts()`
View and manage alerts.

```php
public function alerts(Request $request): Response
public function dismissAlert(Request $request, Alert $alert): RedirectResponse
```

**Routes**:
- `GET /game/alerts` - List alerts
- `POST /game/alerts/{alert}/dismiss` - Dismiss alert

---

### LogisticsController

**File**: `app/Http/Controllers/LogisticsController.php`

Handles multi-hop routing and logistics queries.

**Methods**:

#### `index()`
Displays logistics overview.

```php
public function index(Request $request): Response
```

**Route**: `GET /game/logistics`

**Returns**: Inertia page with routes, shipments, and capacity analysis

#### `findPaths()`
Finds available shipping paths between two locations.

```php
public function findPaths(Request $request): JsonResponse
```

**Route**: `POST /game/logistics/find-paths`

**Request**:
```json
{
    "origin_id": "loc-1",
    "destination_id": "loc-3",
    "departure_day": 5
}
```

**Response**:
```json
{
    "paths": [
        {
            "routes": ["route-1", "route-2"],
            "total_days": 4,
            "total_cost": 75.00,
            "legs": [
                {
                    "route_id": "route-1",
                    "origin": "loc-1",
                    "destination": "loc-2",
                    "transit_days": 2,
                    "cost": 35.00
                },
                // ...
            ]
        }
    ]
}
```

**Process**:
1. Validates origin and destination
2. Calls `LogisticsService@findAllPaths()`
3. Filters by available capacity
4. Returns sorted paths (fastest first)

#### `checkCapacity()`
Checks route capacity for a specific day.

```php
public function checkCapacity(Request $request): JsonResponse
```

**Route**: `POST /game/logistics/check-capacity`

**Request**:
```json
{
    "route_id": "route-1",
    "day": 5
}
```

**Response**:
```json
{
    "available": true,
    "capacity": 10,
    "used": 3,
    "remaining": 7
}
```

---

### Settings Controllers

Located in `app/Http/Controllers/Settings/`, these handle user profile and security settings.

#### ProfileController
Manages user profile updates.

**Routes**:
- `GET /profile` - View profile
- `PUT /profile` - Update profile
- `DELETE /profile` - Delete account

#### PasswordController
Manages password changes.

**Routes**:
- `GET /user-password` - View password change form
- `PUT /user-password` - Update password

#### TwoFactorAuthenticationController
Manages 2FA setup.

**Routes**:
- `GET /settings/two-factor` - View 2FA settings
- `POST /settings/two-factor` - Enable 2FA
- `DELETE /settings/two-factor` - Disable 2FA

---

## Middleware

### HandleInertiaRequests

**File**: `app/Http/Middleware/HandleInertiaRequests.php`

Shares global data with all Inertia responses.

**Shared Props**:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ] : null,
        ],
        'flash' => [
            'message' => fn () => $request->session()->get('message'),
            'error' => fn () => $request->session()->get('error'),
        ],
        'ziggy' => fn () => [
            ...(new Ziggy)->toArray(),
            'location' => $request->url(),
        ],
    ]);
}
```

All React pages have access to these props via `usePage()`.

### HandleAppearance

**File**: `app/Http/Middleware/HandleAppearance.php`

Manages user's appearance preferences (theme, layout).

---

## Route Organization

Routes are organized in `routes/web.php`:

### Public Routes
```php
Route::get('/', [WelcomeController::class, '__invoke'])->name('welcome');
```

### Authentication Routes
Laravel Fortify handles auth routes automatically:
- `/login` - Login page
- `/register` - Registration page
- `/forgot-password` - Password reset request
- `/reset-password/{token}` - Password reset form
- `/verify-email` - Email verification prompt
- `/two-factor-challenge` - 2FA challenge

### Authenticated Routes
```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Game routes
    Route::prefix('game')->name('game.')->group(function () {
        Route::get('/', [GameController::class, 'index'])->name('index');
        Route::post('/initialize', [GameController::class, 'initialize'])->name('initialize');
        Route::post('/advance-time', [GameController::class, 'advanceTime'])->name('advance-time');

        // Orders
        Route::get('/orders', [GameController::class, 'orders'])->name('orders');
        Route::post('/orders', [GameController::class, 'storeOrder'])->name('orders.store');
        Route::post('/orders/{order}/cancel', [GameController::class, 'cancelOrder'])->name('orders.cancel');

        // Transfers
        Route::get('/transfers', [GameController::class, 'transfers'])->name('transfers');
        Route::post('/transfers', [GameController::class, 'storeTransfer'])->name('transfers.store');

        // Logistics
        Route::get('/logistics', [LogisticsController::class, 'index'])->name('logistics');
        Route::post('/logistics/find-paths', [LogisticsController::class, 'findPaths'])->name('logistics.find-paths');
        Route::post('/logistics/check-capacity', [LogisticsController::class, 'checkCapacity'])->name('logistics.check-capacity');

        // Policy & Alerts
        Route::get('/policy', [GameController::class, 'policy'])->name('policy');
        Route::post('/policy', [GameController::class, 'updatePolicy'])->name('policy.update');
        Route::get('/alerts', [GameController::class, 'alerts'])->name('alerts');
        Route::post('/alerts/{alert}/dismiss', [GameController::class, 'dismissAlert'])->name('alerts.dismiss');
    });

    // Settings routes
    Route::prefix('settings')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('/password', [PasswordController::class, 'edit'])->name('user-password');
        Route::put('/password', [PasswordController::class, 'update'])->name('user-password.update');

        Route::get('/two-factor', [TwoFactorAuthenticationController::class, 'show'])->name('two-factor');
        Route::post('/two-factor', [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.store');
        Route::delete('/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.destroy');
    });
});
```

## Wayfinder Integration

Routes are automatically available in React via Wayfinder:

**TypeScript Usage**:
```typescript
import { route } from '@/wayfinder';

// Navigate to game page
router.visit(route('game.index'));

// Create order
router.post(route('game.orders.store'), {
    location_id: 'loc-1',
    vendor_id: 'sup-1',
    items: [/* ... */]
});

// Find paths
axios.post(route('game.logistics.find-paths'), {
    origin_id: 'loc-1',
    destination_id: 'loc-3',
    departure_day: 5
});
```

## Response Patterns

### Inertia Response
```php
return Inertia::render('game/inventory', [
    'gameState' => $gameState,
    'locations' => $locations,
    'products' => $products,
    // ...
]);
```

### Redirect with Flash
```php
return redirect()
    ->route('game.index')
    ->with('message', 'Order placed successfully!');
```

### JSON Response (for AJAX)
```php
return response()->json([
    'paths' => $paths,
    'success' => true
]);
```

### Error Response
```php
return back()
    ->withErrors(['error' => 'Insufficient funds'])
    ->withInput();
```

## Authorization

Controllers use Laravel policies for authorization:

```php
public function cancelOrder(Order $order): RedirectResponse
{
    // Authorize user owns this order
    $this->authorize('cancel', $order);

    // ... cancel logic
}
```

Policy definition:
```php
// app/Policies/OrderPolicy.php
public function cancel(User $user, Order $order): bool
{
    return $user->id === $order->user_id && $order->canCancel();
}
```

## Error Handling

### Validation Errors
Handled automatically by FormRequest classes. Errors are shared with Inertia and accessible in React via `usePage().props.errors`.

### Exception Handling
Laravel's exception handler catches exceptions. In production, generic error pages are shown. In development, detailed error pages with stack traces.

### Custom Errors
```php
if ($gameState->cash < $totalCost) {
    throw ValidationException::withMessages([
        'cash' => 'Insufficient funds to place this order.'
    ]);
}
```

## Related Documentation

- [Services](./04-services.md)
- [Models & Database](./02-models-database.md)
- [API Reference](./07-api-reference.md)
