# Backend Architecture

## Overview

The Moonshine Coffee Management Sim backend is built on Laravel 12, following a **Service-Oriented Architecture** with clear separation of concerns. The architecture emphasizes maintainability, testability, and scalability while leveraging Laravel's powerful features.

## Architectural Layers

```
┌─────────────────────────────────────────────────────────┐
│                     Presentation Layer                   │
│  (Inertia.js Controllers, Form Requests, Middleware)     │
└─────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────┐
│                     Service Layer                        │
│  (Business Logic, Complex Operations, AI Integration)    │
└─────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────┐
│                     Domain Layer                         │
│  (Models, State Machines, Events, Observers)            │
└─────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────┐
│                     Data Layer                           │
│  (Database, Eloquent ORM, Migrations, Factories)        │
└─────────────────────────────────────────────────────────┘
```

## Core Design Patterns

### 1. Service-Oriented Architecture

Complex business logic is extracted into dedicated service classes, keeping controllers thin and focused on HTTP concerns.

**Example Services:**
- `SimulationService` - Game simulation and time advancement
- `OrderService` - Order placement and validation
- `LogisticsService` - Multi-hop routing and shipment management
- `InventoryManagementService` - Inventory analysis and AI advisories
- `PrismAiService` - AI integration wrapper

**Benefits:**
- Separation of concerns
- Testability (easy to unit test)
- Reusability across controllers and commands
- Dependency injection support

### 2. Event-Driven Architecture

The game simulation heavily relies on Laravel's event system to handle complex workflows and side effects.

**Key Events:**
- `TimeAdvanced` - Triggered when game time moves forward
- `OrderPlaced` - When a new order is created
- `OrderDelivered` - When an order arrives
- `TransferCompleted` - When a transfer finishes
- `SpikeOccurred` / `SpikeEnded` - Spike event lifecycle
- `LowStockDetected` - When inventory falls below threshold

**Benefits:**
- Decoupled components
- Easy to add new features
- Async processing support
- Clear audit trail

### 3. State Machine Pattern

Orders and transfers use state machine patterns for managing their lifecycle.

**Order States:**
```
Draft → Pending → Shipped → Delivered
             ↓
         Cancelled
```

**Transfer States:**
```
Draft → InTransit → Completed
            ↓
        Cancelled
```

**Implementation:**
- State classes in `app/States/`
- Transition classes in `app/States/{Entity}/Transitions/`
- Validates state transitions
- Triggers events on transitions
- Prevents invalid state changes

### 4. Repository Pattern (via Eloquent)

While we don't use a traditional repository pattern, Eloquent models serve as our data repositories with:
- Query scopes for complex queries
- Relationships for data access
- Accessors/Mutators for data transformation
- Model observers for lifecycle hooks

### 5. Data Transfer Objects (DTOs)

DTOs provide type-safe data structures for complex data passing between layers.

**Examples:**
- `InventoryContextDTO` - Inventory analysis context
- `InventoryAdvisoryDTO` - AI-generated inventory recommendations

**Benefits:**
- Type safety
- IDE autocompletion
- Clear data contracts
- Easier refactoring

## Directory Structure

```
app/
├── Actions/              # Single-action classes (Fortify, game initialization)
│   ├── Fortify/
│   ├── GenerateIsolationAlerts.php
│   └── InitializeNewGame.php
├── Console/
│   └── Commands/         # Artisan commands
├── DTOs/                 # Data Transfer Objects
│   ├── InventoryAdvisoryDTO.php
│   └── InventoryContextDTO.php
├── Events/               # Domain events
│   ├── LowStockDetected.php
│   ├── OrderCancelled.php
│   ├── OrderDelivered.php
│   ├── OrderPlaced.php
│   ├── SpikeEnded.php
│   ├── SpikeOccurred.php
│   ├── TimeAdvanced.php
│   └── TransferCompleted.php
├── Http/
│   ├── Controllers/      # HTTP request handlers
│   │   ├── GameController.php
│   │   ├── LogisticsController.php
│   │   ├── WelcomeController.php
│   │   └── Settings/
│   ├── Middleware/       # HTTP middleware
│   │   ├── HandleAppearance.php
│   │   └── HandleInertiaRequests.php
│   └── Requests/         # Form request validation
│       ├── StoreOrderRequest.php
│       └── Settings/
├── Interfaces/           # Contracts/Interfaces
│   ├── AiProviderInterface.php
│   ├── RestockStrategyInterface.php
│   └── SpikeTypeInterface.php
├── Listeners/            # Event listeners
│   ├── ApplySpikeEffect.php
│   ├── ApplyStorageCosts.php
│   ├── DecayPerishables.php
│   ├── DeductCash.php
│   ├── GenerateAlert.php
│   ├── GenerateSpike.php
│   ├── ProcessDeliveries.php
│   ├── RollbackSpikeEffect.php
│   ├── UpdateInventory.php
│   └── UpdateMetrics.php
├── Models/               # Eloquent models
│   ├── Alert.php
│   ├── GameState.php
│   ├── Inventory.php
│   ├── Location.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Product.php
│   ├── Route.php
│   ├── Shipment.php
│   ├── SpikeEvent.php
│   ├── Transfer.php
│   ├── User.php
│   └── Vendor.php
├── Observers/            # Model observers
│   └── InventoryObserver.php
├── Providers/            # Service providers
│   ├── AppServiceProvider.php
│   ├── FortifyServiceProvider.php
│   └── GameServiceProvider.php
├── Services/             # Business logic services
│   ├── GuaranteedSpikeGenerator.php
│   ├── InventoryManagementService.php
│   ├── InventoryMathService.php
│   ├── LogisticsService.php
│   ├── OrderService.php
│   ├── PrismAiService.php
│   ├── SimulationService.php
│   ├── SpikeConstraintChecker.php
│   ├── SpikeEventFactory.php
│   ├── Spikes/          # Spike type implementations
│   │   ├── BlizzardSpike.php
│   │   ├── BreakdownSpike.php
│   │   ├── DelaySpike.php
│   │   ├── DemandSpike.php
│   │   └── PriceSpike.php
│   └── Strategies/      # Strategy pattern implementations
│       ├── JustInTimeStrategy.php
│       └── SafetyStockStrategy.php
└── States/               # State machine states
    ├── Order/
    │   ├── Cancelled.php
    │   ├── Delivered.php
    │   ├── Draft.php
    │   ├── Pending.php
    │   ├── Shipped.php
    │   └── Transitions/
    │       ├── ToCancelled.php
    │       ├── ToDelivered.php
    │       ├── ToPending.php
    │       └── ToShipped.php
    ├── OrderState.php
    ├── Transfer/
    │   ├── Cancelled.php
    │   ├── Completed.php
    │   ├── Draft.php
    │   ├── InTransit.php
    │   └── Transitions/
    │       └── ToCompleted.php
    └── TransferState.php
```

## Data Flow

### 1. HTTP Request Flow

```
User Action (React)
    ↓
Inertia.js Router
    ↓
Laravel Route → Middleware
    ↓
Controller Method
    ↓
Form Request Validation (if applicable)
    ↓
Service Layer (business logic)
    ↓
Model/Database
    ↓
Events Dispatched
    ↓
Inertia Response with Props
    ↓
React Component Re-render
```

### 2. Game Simulation Flow

```
Advance Day Button Clicked
    ↓
GameController@advanceTime
    ↓
SimulationService@advanceDay()
    ↓
TimeAdvanced Event Dispatched
    ↓
Multiple Listeners Execute:
    - ProcessDeliveries (update orders/transfers)
    - DecayPerishables (reduce inventory)
    - ApplyStorageCosts (deduct cash)
    - GenerateSpike (random spike events)
    - UpdateMetrics (recalculate stats)
    ↓
State Updated in Database
    ↓
Response with Fresh Game State
    ↓
React Updates UI
```

### 3. Order Placement Flow

```
NewOrderDialog Submit
    ↓
POST /game/orders
    ↓
StoreOrderRequest Validation
    ↓
OrderService@createOrder()
    - Validate inventory capacity
    - Calculate costs
    - Determine delivery route
    - Create shipments
    ↓
Order Model Created
    ↓
OrderPlaced Event
    ↓
Listeners:
    - DeductCash
    - UpdateMetrics
    - GenerateAlert (if needed)
    ↓
Inertia Response
    ↓
UI Updated with New Order
```

## Key Architectural Decisions

### 1. Inertia.js Over REST API

**Decision**: Use Inertia.js instead of building a separate REST API

**Rationale**:
- Simpler architecture (no API versioning, authentication complexity)
- Type-safe props from backend to frontend
- Built-in CSRF protection
- Server-side rendering support
- Faster development

### 2. Event-Driven Game Mechanics

**Decision**: Use Laravel events for game mechanics instead of procedural code

**Rationale**:
- Decoupled components
- Easy to add new game mechanics
- Clear separation of concerns
- Testable in isolation
- Async processing capability

### 3. Service Layer for Business Logic

**Decision**: Extract complex logic into service classes

**Rationale**:
- Keeps controllers thin
- Reusable across different entry points
- Easier to test
- Better separation of concerns
- Dependency injection support

### 4. State Machines for Workflows

**Decision**: Use state machine pattern for orders and transfers

**Rationale**:
- Prevents invalid state transitions
- Centralizes transition logic
- Easy to audit state changes
- Clear state transition rules
- Event emission on transitions

### 5. PostgreSQL Over MySQL

**Decision**: Use PostgreSQL as the database

**Rationale**:
- Better JSON support for metadata
- Advanced indexing capabilities
- Better data integrity
- Superior query optimizer
- Array and JSONB data types

## Performance Considerations

### 1. Eager Loading

Always use eager loading to prevent N+1 queries:

```php
// Good
$orders = Order::with(['items.product', 'vendor', 'location'])->get();

// Bad
$orders = Order::all(); // N+1 queries when accessing relations
```

### 2. Query Optimization

- Add database indexes on frequently queried columns
- Use `select()` to limit fetched columns
- Use `chunk()` for large datasets
- Cache expensive queries

### 3. Event Queue

For production, process heavy listeners asynchronously:

```php
class ProcessDeliveries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Listener logic
}
```

### 4. Pagination

Use Laravel's pagination for large datasets:

```php
$orders = Order::latest()->paginate(20);
```

## Security Considerations

### 1. CSRF Protection

Automatic via Inertia.js and Laravel's CSRF middleware.

### 2. Authorization

Use Laravel policies for model authorization:

```php
// In policy
public function update(User $user, Order $order)
{
    return $user->id === $order->user_id;
}

// In controller
$this->authorize('update', $order);
```

### 3. Mass Assignment Protection

Always define `$fillable` or `$guarded` on models:

```php
protected $fillable = ['location_id', 'vendor_id', 'status'];
```

### 4. Input Validation

Always use FormRequest classes for validation:

```php
class StoreOrderRequest extends FormRequest
{
    public function rules()
    {
        return [
            'location_id' => 'required|exists:locations,id',
            'items' => 'required|array|min:1',
            // ...
        ];
    }
}
```

## Testing Strategy

### Unit Tests

Test services and models in isolation:

```php
test('calculates order total correctly', function () {
    $service = new OrderService();
    $total = $service->calculateTotal($items);
    expect($total)->toBe(150.00);
});
```

### Feature Tests

Test HTTP endpoints end-to-end:

```php
test('can place an order', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/game/orders', [
            'location_id' => 'loc-1',
            'items' => [/*...*/]
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('orders', ['location_id' => 'loc-1']);
});
```

### Integration Tests

Test complex workflows:

```php
test('order delivery updates inventory', function () {
    Event::fake([OrderDelivered::class]);

    $order = Order::factory()->create(['status' => 'Shipped']);

    event(new OrderDelivered($order));

    Event::assertDispatched(OrderDelivered::class);
    // Assert inventory updated
});
```

## Related Documentation

- [Models & Database](./02-models-database.md)
- [Services](./04-services.md)
- [Events & Listeners](./05-events-listeners.md)
