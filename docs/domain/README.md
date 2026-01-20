# Domain Documentation

This directory contains comprehensive documentation for the business logic and game mechanics of the Moonshine Coffee Management Sim.

## Overview

The **domain layer** encompasses the core business rules, game mechanics, and simulation logic that drive the coffee shop management experience. This layer is independent of the presentation layer and can be tested in isolation.

## Core Concepts

The game simulates a multi-location coffee shop supply chain with the following key elements:

1. **Inventory Management** - Track stock levels across multiple locations
2. **Supply Chain** - Order from vendors, manage lead times and costs
3. **Logistics** - Multi-hop routing between locations
4. **Game Events (Spikes)** - Dynamic events affecting gameplay (demand spikes, delays, price changes)
5. **Resource Management** - Balance cash, storage capacity, and perishability
6. **Decision Support** - AI-powered recommendations and analytics

## Game Flow

```
┌─────────────────────────────────────────────────────────────┐
│                      Game Initialization                     │
│  - Set starting cash ($5,000)                                │
│  - Create locations with initial inventory                   │
│  - Set up vendor relationships and pricing                   │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Daily Operations                        │
│  1. Player reviews inventory across locations                │
│  2. Checks alerts and recommendations                        │
│  3. Places orders from vendors                               │
│  4. Initiates internal transfers                             │
│  5. Adjusts inventory policies                               │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Time Advancement                        │
│  Player clicks "Advance Day" button                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Simulation Processing                     │
│  1. Process deliveries (orders and transfers)                │
│  2. Update inventory levels                                  │
│  3. Apply storage costs                                      │
│  4. Decay perishable items                                   │
│  5. Generate spike events (random)                           │
│  6. Apply spike effects                                      │
│  7. Generate alerts and recommendations                      │
│  8. Update game metrics                                      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
                   (Loop back to Daily Operations)
```

## Documentation Files

1. **[Game Mechanics](./01-game-mechanics.md)** - Core game rules, win/lose conditions, scoring
2. **[Inventory System](./02-inventory-system.md)** - Inventory management, reorder points, safety stock
3. **[Supply Chain](./03-supply-chain.md)** - Ordering, vendor management, pricing tiers
4. **[Logistics](./04-logistics.md)** - Multi-hop routing, transfer system, capacity management
5. **[Spike Events](./05-spike-events.md)** - Dynamic game events and their effects
6. **[Decision Support](./06-decision-support.md)** - AI recommendations, analytics, KPIs
7. **[Business Rules](./07-business-rules.md)** - Core business logic and calculations

## Key Domain Services

### SimulationService
Orchestrates the game simulation, time advancement, and event processing.

**Location**: `app/Services/SimulationService.php`

**Responsibilities**:
- Advance game time
- Process deliveries
- Trigger time-based events
- Coordinate event listeners

### OrderService
Manages order placement and validation.

**Location**: `app/Services/OrderService.php`

**Responsibilities**:
- Validate order requests
- Calculate order costs
- Determine delivery routes
- Create shipments

### LogisticsService
Handles multi-hop routing and capacity management.

**Location**: `app/Services/LogisticsService.php`

**Responsibilities**:
- Find shipping paths between locations
- Calculate shipping costs
- Check route capacity
- Optimize routing

### InventoryManagementService
Provides AI-powered inventory insights and recommendations.

**Location**: `app/Services/InventoryManagementService.php`

**Responsibilities**:
- Analyze inventory positions
- Generate restock recommendations
- Calculate reorder points
- Identify risks (stockouts, expiry)

### GuaranteedSpikeGenerator
Generates game events according to configured rules.

**Location**: `app/Services/GuaranteedSpikeGenerator.php`

**Responsibilities**:
- Generate spike events
- Respect spike constraints (cooldowns, dependencies)
- Ensure game balance
- Apply spike effects

## Game Parameters

### Starting Conditions
- **Cash**: $5,000
- **Day**: 0
- **Reputation**: 50/100

### Locations
1. **Moonshine HQ (Roastery)** - Storage: 5,000 units
2. **Uptown Kiosk** - Storage: 500 units
3. **Lakeside Cafe** - Storage: 1,200 units

### Vendors
1. **BeanCo Global** - Premium beans, reliable, standard speed
2. **RapidSupplies** - Fast delivery, general supplies
3. **Dairy Direct** - Milk specialist, very reliable
4. **ValueBulk** - Cheap but slow and unreliable

### Products (11 SKUs)
Categories: Beans, Milk, Cups, Syrup, Tea, Sugar, Cleaning, Seasonal, Food, Sauce

### Routes
- Roastery ↔ Kiosk: 2 days, $35
- Roastery ↔ Cafe: 3 days, $50
- Kiosk ↔ Cafe: 2 days, $30

## Core Formulas

### Reorder Point (ROP)
```
ROP = (Average Daily Usage × Lead Time) + Safety Stock
Safety Stock = Z-score × σ × √Lead Time
```

Where:
- Z-score depends on service level (95% = 1.645)
- σ (sigma) = standard deviation of daily usage

### Days Cover
```
Days Cover = On-Hand Quantity / Average Daily Usage
```

### Storage Cost
```
Daily Storage Cost = Quantity × Storage Cost Per Unit
```

### Order Total Cost
```
Total = Sum(Item Unit Price × Quantity) + Shipping Cost
Shipping Cost = Flat Rate (if below threshold) or Free
```

### Transfer Cost
```
Transfer Cost = Base Route Cost + (Handling Fee × Quantity)
```

## Event System

The game uses Laravel's event system extensively:

**Key Events**:
- `TimeAdvanced` - Day progressed
- `OrderPlaced` - New order created
- `OrderDelivered` - Order arrived
- `TransferCompleted` - Transfer finished
- `SpikeOccurred` - Spike event started
- `SpikeEnded` - Spike event ended
- `LowStockDetected` - Inventory below threshold

**Event Flow**:
```
Action Triggered
    ↓
Event Dispatched
    ↓
Multiple Listeners Execute
    ↓
Side Effects Applied
    ↓
State Updated
```

## Game Balance

### Difficulty Factors
1. **Storage Constraints** - Limited space forces strategic planning
2. **Perishability** - Some items expire, creating waste if over-ordered
3. **Lead Times** - Vendor delivery times vary (1-7 days)
4. **Cash Management** - Must balance spending vs. reserves
5. **Spike Events** - Random events create challenges
6. **Vendor Reliability** - Unreliable vendors may delay orders

### Win Conditions
The game is open-ended, but success metrics include:
- Maintaining positive cash flow
- Minimizing waste
- Avoiding stockouts
- Building reputation
- Responding effectively to spikes

### Lose Conditions
- Cash reaches $0 (bankruptcy)
- Critical stockouts affecting operations
- Excessive waste degrading reputation

## AI Integration

The game uses **Prism AI** (via EchoLabs) for:
- Inventory advisory generation
- Natural language explanations
- Anomaly detection
- Decision support

**Prompt Example**:
```
Analyze this inventory situation and provide:
1. Current risk assessment
2. Recommended action
3. Rationale for recommendation
4. Potential consequences of inaction
```

## Testing Domain Logic

Domain logic should be tested independently of the presentation layer:

**Unit Tests**:
```php
test('calculates reorder point correctly', function () {
    $rop = calculateReorderPoint(
        avgDailyUsage: 10,
        leadTimeDays: 5,
        serviceLevel: 0.95
    );
    expect($rop)->toBeGreaterThan(50);
});
```

**Feature Tests**:
```php
test('advancing time processes deliveries', function () {
    $gameState = GameState::factory()->create(['day' => 5]);
    $order = Order::factory()->create([
        'delivery_day' => 6,
        'status' => 'Shipped'
    ]);

    $gameState->advanceDay();

    expect($order->fresh()->status)->toBe('Delivered');
});
```

## Related Documentation

- [Backend Architecture](../backend/)
- [Frontend Architecture](../frontend/)
- [Technical Design Document](../technical-design-document.md)
