# Models & Database Schema

## Overview

The database schema is designed to support a multi-location coffee shop simulation with inventory management, supply chain logistics, and dynamic game events. The schema uses PostgreSQL and is managed through Laravel migrations.

## Entity Relationship Diagram

```
┌──────────────┐
│    User      │
│──────────────│
│ id           │──┐
│ name         │  │
│ email        │  │
│ password     │  │
└──────────────┘  │
                  │
┌──────────────┐  │
│  GameState   │  │
│──────────────│  │
│ id           │  │
│ user_id      │◄─┘
│ cash         │
│ day          │
│ reputation   │
└──────────────┘

┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Location   │◄────│   Route      │────►│   Location   │
│──────────────│     │──────────────│     │──────────────│
│ id           │     │ id           │     │              │
│ name         │     │ origin_id    │     │              │
│ type         │     │ destination_id│     │              │
│ max_storage  │     │ transit_days │     │              │
└──────────────┘     │ base_cost    │     └──────────────┘
       │             └──────────────┘
       │
       │             ┌──────────────┐
       └────────────►│  Inventory   │
                     │──────────────│
                     │ id           │
                     │ location_id  │
                     │ product_id   │◄───┐
                     │ quantity     │    │
                     │ expiry_date  │    │
                     └──────────────┘    │
                                         │
┌──────────────┐     ┌──────────────┐   │
│   Vendor     │────►│   Product    │───┘
│──────────────│     │──────────────│
│ id           │     │ id           │
│ name         │     │ name         │
│ reliability  │     │ category     │
│ speed        │     │ unit         │
└──────────────┘     │ is_perishable│
       │             │ shelf_life   │
       │             └──────────────┘
       │                     │
       │                     │
┌──────────────┐            │
│ ProductVendor│            │
│──────────────│            │
│ vendor_id    │            │
│ product_id   │────────────┘
│ price        │
│ min_qty      │
│ lead_days    │
└──────────────┘

┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│    Order     │────►│  OrderItem   │────►│   Product    │
│──────────────│     │──────────────│     │──────────────│
│ id           │     │ id           │     │              │
│ location_id  │     │ order_id     │     │              │
│ vendor_id    │     │ product_id   │     │              │
│ status       │     │ quantity     │     │              │
│ placed_day   │     │ unit_price   │     │              │
│ delivery_day │     └──────────────┘     │              │
│ total_cost   │                          │              │
└──────────────┘                          └──────────────┘
       │
       │
       │             ┌──────────────┐
       └────────────►│   Shipment   │
                     │──────────────│
                     │ id           │
                     │ order_id     │
                     │ route_id     │
                     │ status       │
                     │ arrival_day  │
                     └──────────────┘

┌──────────────┐
│   Transfer   │
│──────────────│
│ id           │
│ from_loc_id  │
│ to_loc_id    │
│ product_id   │
│ quantity     │
│ status       │
│ created_day  │
│ delivery_day │
└──────────────┘

┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  SpikeEvent  │────►│    Alert     │
│──────────────│     │──────────────│
│ id           │     │ id           │
│ type         │     │ spike_id     │
│ location_id  │     │ type         │
│ product_id   │     │ severity     │
│ active       │     │ message      │
│ start_day    │     │ dismissed    │
│ end_day      │     └──────────────┘
│ multiplier   │
│ meta         │
└──────────────┘

┌──────────────┐
│ DailyReport  │
│──────────────│
│ id           │
│ user_id      │
│ day          │
│ summary_data │
│ metrics      │
└──────────────┘
```

## Core Models

### User

Represents a player/user account.

**File**: `app/Models/User.php`

**Columns**:
- `id` - Primary key
- `name` - User's name
- `email` - User's email (unique)
- `password` - Hashed password
- `remember_token` - For "remember me" functionality
- `two_factor_secret` - 2FA secret (encrypted)
- `two_factor_recovery_codes` - 2FA recovery codes (encrypted)
- `two_factor_confirmed_at` - When 2FA was enabled
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `hasOne(GameState)` - User's game state
- `hasMany(Alert)` - User's alerts

**Key Methods**:
```php
public function gameState(): HasOne
public function alerts(): HasMany
```

---

### GameState

Represents the current game state for a user.

**File**: `app/Models/GameState.php`

**Columns**:
- `id` - Primary key
- `user_id` - Foreign key to users
- `cash` - Current cash balance (decimal:2)
- `day` - Current game day (integer)
- `reputation` - Reputation score 0-100 (decimal)
- `last_spike_day` - Last day a spike occurred
- `spike_count` - Total spikes generated
- `spike_config` - Spike generation configuration (JSON)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - The owning user

**Casts**:
```php
protected $casts = [
    'spike_config' => 'array',
    'cash' => 'decimal:2',
    'reputation' => 'decimal:2'
];
```

**Key Methods**:
```php
public function user(): BelongsTo
public function advanceDay(int $days = 1): void
public function deductCash(float $amount): void
public function addCash(float $amount): void
```

---

### Location

Represents a physical location (Roastery HQ, Kiosk, Cafe).

**File**: `app/Models/Location.php`

**Columns**:
- `id` - Primary key (string ID like 'loc-1')
- `name` - Location name
- `address` - Physical address
- `type` - Location type: 'roastery', 'kiosk', 'cafe'
- `max_storage` - Maximum storage capacity
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `hasMany(Inventory)` - Inventory at this location
- `hasMany(Order)` - Orders for this location
- `hasMany(Transfer, 'from_location_id')` - Outgoing transfers
- `hasMany(Transfer, 'to_location_id')` - Incoming transfers
- `hasMany(Route, 'origin_id')` - Routes from this location
- `hasMany(Route, 'destination_id')` - Routes to this location

**Key Methods**:
```php
public function inventory(): HasMany
public function orders(): HasMany
public function outgoingTransfers(): HasMany
public function incomingTransfers(): HasMany
public function getTotalStorageUsed(): int
public function hasCapacityFor(int $quantity): bool
```

---

### Product

Represents an item/SKU in the inventory system.

**File**: `app/Models/Product.php`

**Columns**:
- `id` - Primary key (string ID like 'item-1')
- `name` - Product name
- `category` - Category (beans, milk, cups, etc.)
- `unit` - Unit of measure
- `is_perishable` - Boolean flag
- `shelf_life_days` - Estimated shelf life
- `bulk_threshold` - Quantity threshold for bulk
- `storage_cost_per_unit` - Cost per unit per day (decimal:2)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `hasMany(Inventory)` - Inventory records
- `hasMany(OrderItem)` - Order line items
- `belongsToMany(Vendor)` - Available vendors (pivot: product_vendor)

**Casts**:
```php
protected $casts = [
    'is_perishable' => 'boolean',
    'storage_cost_per_unit' => 'decimal:2'
];
```

**Key Methods**:
```php
public function vendors(): BelongsToMany
public function inventoryAt(Location $location): ?Inventory
public function totalOnHand(): int
```

---

### Vendor (Supplier)

Represents a supplier/vendor.

**File**: `app/Models/Vendor.php`

**Columns**:
- `id` - Primary key (string ID like 'sup-1')
- `name` - Vendor name
- `reliability` - Reliability score 0-1 (decimal)
- `delivery_speed` - 'fast', 'standard', 'slow'
- `free_shipping_threshold` - Minimum for free shipping (decimal)
- `flat_shipping_rate` - Flat shipping cost (decimal)
- `description` - Vendor description (text)
- `contact_name`, `contact_email`, `phone` - Contact info
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsToMany(Product)` - Products offered (pivot: product_vendor)
- `hasMany(Order)` - Orders placed with this vendor

**Key Methods**:
```php
public function products(): BelongsToMany
public function orders(): HasMany
public function getProductPrice(Product $product, int $quantity): float
public function getDeliveryDays(Product $product): int
```

---

### ProductVendor (Pivot)

Pivot table connecting products to vendors with pricing info.

**Table**: `product_vendor`

**Columns**:
- `vendor_id` - Foreign key to vendors
- `product_id` - Foreign key to products
- `price_per_unit` - Base unit price (decimal)
- `min_order_qty` - Minimum order quantity
- `delivery_days` - Delivery lead time in days
- `price_tiers` - Bulk pricing tiers (JSON)

**Casts**:
```php
protected $casts = [
    'price_tiers' => 'array',
    'price_per_unit' => 'decimal:2'
];
```

---

### Inventory

Represents inventory at a specific location.

**File**: `app/Models/Inventory.php`

**Columns**:
- `id` - Primary key
- `location_id` - Foreign key to locations
- `product_id` - Foreign key to products
- `quantity` - Current quantity
- `expiry_date` - Expiration date for perishables (nullable)
- `last_restocked` - Last restock date
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(Location)` - Location
- `belongsTo(Product)` - Product

**Observers**: `InventoryObserver` - Triggers low stock events

**Key Methods**:
```php
public function location(): BelongsTo
public function product(): BelongsTo
public function addQuantity(int $amount): void
public function reduceQuantity(int $amount): void
public function isExpiringSoon(int $days = 7): bool
public function daysUntilExpiry(): ?int
```

**Scopes**:
```php
public function scopeLowStock(Builder $query): Builder
public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
```

---

### Order

Represents a purchase order from a vendor.

**File**: `app/Models/Order.php`

**Columns**:
- `id` - Primary key (UUID)
- `user_id` - Foreign key to users
- `location_id` - Destination location
- `vendor_id` - Foreign key to vendors
- `status` - Order status (state machine)
- `placed_day` - Day order was placed
- `delivery_day` - Expected delivery day
- `total_cost` - Total order cost (decimal:2)
- `notes` - Optional notes (text)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - Ordering user
- `belongsTo(Location)` - Destination location
- `belongsTo(Vendor)` - Vendor
- `hasMany(OrderItem)` - Line items
- `hasMany(Shipment)` - Shipments (for multi-hop orders)

**State Machine**: Uses `OrderState` for status transitions

**Casts**:
```php
protected $casts = [
    'total_cost' => 'decimal:2',
    'placed_day' => 'integer',
    'delivery_day' => 'integer'
];
```

**Key Methods**:
```php
public function items(): HasMany
public function shipments(): HasMany
public function canCancel(): bool
public function markAsShipped(): void
public function markAsDelivered(): void
public function calculateTotal(): float
```

**Events**:
- `OrderPlaced` - When order is created
- `OrderDelivered` - When order arrives
- `OrderCancelled` - When order is cancelled

---

### OrderItem

Represents a line item in an order.

**File**: `app/Models/OrderItem.php`

**Columns**:
- `id` - Primary key
- `order_id` - Foreign key to orders
- `product_id` - Foreign key to products
- `quantity` - Quantity ordered
- `unit_price` - Price per unit (decimal:2)
- `subtotal` - Line item subtotal (decimal:2)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(Order)` - Parent order
- `belongsTo(Product)` - Product

**Casts**:
```php
protected $casts = [
    'unit_price' => 'decimal:2',
    'subtotal' => 'decimal:2'
];
```

---

### Route

Represents a shipping route between two locations.

**File**: `app/Models/Route.php`

**Columns**:
- `id` - Primary key
- `origin_id` - Origin location ID
- `destination_id` - Destination location ID
- `transit_days` - Transit time in days
- `base_cost` - Base shipping cost (decimal:2)
- `capacity_per_day` - Maximum shipments per day
- `is_active` - Whether route is active (boolean)
- `notes` - Optional notes (text)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(Location, 'origin_id')` - Origin location
- `belongsTo(Location, 'destination_id')` - Destination location
- `hasMany(Shipment)` - Shipments using this route

**Key Methods**:
```php
public function origin(): BelongsTo
public function destination(): BelongsTo
public function shipments(): HasMany
public function hasCapacityOn(int $day): bool
public function calculateCost(float $weight): float
```

---

### Shipment

Represents a shipment leg in a multi-hop order.

**File**: `app/Models/Shipment.php`

**Columns**:
- `id` - Primary key
- `order_id` - Foreign key to orders
- `route_id` - Foreign key to routes
- `sequence` - Leg sequence number (for multi-hop)
- `status` - Shipment status
- `departure_day` - Day shipped
- `arrival_day` - Expected arrival day
- `actual_arrival_day` - Actual arrival day (nullable)
- `current_location_id` - Current location
- `notes` - Optional notes (text)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(Order)` - Parent order
- `belongsTo(Route)` - Shipping route
- `belongsTo(Location, 'current_location_id')` - Current location

**Key Methods**:
```php
public function order(): BelongsTo
public function route(): BelongsTo
public function currentLocation(): BelongsTo
public function isInTransit(): bool
public function isDelivered(): bool
public function markAsDelivered(int $day): void
```

---

### Transfer

Represents an internal transfer between locations.

**File**: `app/Models/Transfer.php`

**Columns**:
- `id` - Primary key (UUID)
- `user_id` - Foreign key to users
- `from_location_id` - Source location
- `to_location_id` - Destination location
- `product_id` - Foreign key to products
- `quantity` - Quantity to transfer
- `status` - Transfer status (state machine)
- `created_day` - Day transfer was created
- `delivery_day` - Expected delivery day
- `cost` - Transfer cost (decimal:2)
- `notes` - Optional notes (text)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - User who created transfer
- `belongsTo(Location, 'from_location_id')` - Source
- `belongsTo(Location, 'to_location_id')` - Destination
- `belongsTo(Product)` - Product being transferred

**State Machine**: Uses `TransferState`

**Casts**:
```php
protected $casts = [
    'cost' => 'decimal:2',
    'created_day' => 'integer',
    'delivery_day' => 'integer'
];
```

**Events**:
- `TransferCompleted` - When transfer arrives

---

### SpikeEvent

Represents a game event (demand spike, delay, etc.).

**File**: `app/Models/SpikeEvent.php`

**Columns**:
- `id` - Primary key
- `user_id` - Foreign key to users
- `type` - Event type: 'demand', 'delay', 'price', 'breakdown', 'blizzard'
- `name` - Event name
- `description` - Event description (text)
- `location_id` - Affected location (nullable)
- `product_id` - Affected product (nullable)
- `vendor_id` - Affected vendor (nullable)
- `route_id` - Affected route (nullable)
- `active` - Whether event is active (boolean)
- `start_day` - Start day
- `end_day` - End day (nullable)
- `duration_days` - Duration in days
- `multiplier` - Effect multiplier (decimal)
- `meta` - Additional metadata (JSON)
- `blocked_by` - IDs of blocking events (JSON array)
- `blocks` - IDs of events this blocks (JSON array)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - User
- `belongsTo(Location)` - Affected location
- `belongsTo(Product)` - Affected product
- `belongsTo(Vendor)` - Affected vendor
- `belongsTo(Route)` - Affected route
- `hasMany(Alert)` - Generated alerts

**Casts**:
```php
protected $casts = [
    'active' => 'boolean',
    'meta' => 'array',
    'blocked_by' => 'array',
    'blocks' => 'array',
    'multiplier' => 'decimal:2'
];
```

**Scopes**:
```php
public function scopeActive(Builder $query): Builder
public function scopeByType(Builder $query, string $type): Builder
```

**Events**:
- `SpikeOccurred` - When spike starts
- `SpikeEnded` - When spike ends

---

### Alert

Represents a notification/alert for the player.

**File**: `app/Models/Alert.php`

**Columns**:
- `id` - Primary key
- `user_id` - Foreign key to users
- `spike_event_id` - Related spike event (nullable)
- `type` - Alert type: 'stockout', 'expiry', 'spike', 'vendor_delay', 'waste'
- `severity` - Severity: 'info', 'warning', 'critical'
- `location_id` - Related location (nullable)
- `product_id` - Related product (nullable)
- `message` - Alert message (text)
- `rationale` - Why this alert was generated (text)
- `action_url` - URL for action button (nullable)
- `action_label` - Label for action button (nullable)
- `dismissed` - Whether dismissed (boolean)
- `created_day` - Game day when created
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - User
- `belongsTo(SpikeEvent)` - Related spike
- `belongsTo(Location)` - Related location
- `belongsTo(Product)` - Related product

**Casts**:
```php
protected $casts = [
    'dismissed' => 'boolean',
    'created_day' => 'integer'
];
```

**Scopes**:
```php
public function scopeActive(Builder $query): Builder // Not dismissed
public function scopeBySeverity(Builder $query, string $severity): Builder
```

---

### DailyReport

Represents a historical snapshot of aggregated daily metrics for analytics.

**File**: `app/Models/DailyReport.php`

**Columns**:
- `id` - Primary key
- `user_id` - Foreign key to users
- `day` - Game day for this report
- `summary_data` - Summary statistics (JSON)
- `metrics` - Aggregated metrics (JSON)
- `created_at`, `updated_at` - Timestamps

**Relationships**:
- `belongsTo(User)` - User

**Casts**:
```php
protected $casts = [
    'summary_data' => 'array',
    'metrics' => 'array'
];
```

**Purpose**:
- Created by `CreateDailyReport` listener on `TimeAdvanced` event
- Stores aggregated daily metrics for analytics reporting
- Used by Analytics page for historical trend analysis
- Includes data like total cash spent, orders placed, transfers completed, etc.

---

## Analytics Tables

### inventory_history (Direct DB Table)

**Note**: This table does NOT have an Eloquent model. It uses direct database inserts for performance optimization.

**Table**: `inventory_history`

**Columns**:
- `id` - Primary key
- `user_id` - Foreign key to users
- `location_id` - Foreign key to locations
- `product_id` - Foreign key to products
- `day` - Game day for this snapshot
- `quantity` - Inventory quantity at this day
- `created_at`, `updated_at` - Timestamps

**Unique Constraint**: `(user_id, location_id, product_id, day)` - Prevents duplicate snapshots

**Usage Pattern**:
- Populated by `SnapshotInventoryLevels` listener using direct `DB::table()` inserts
- Queried directly via `DB::table('inventory_history')` in `GameController::getInventoryTrends()`
- High-volume time-series data optimized for write performance
- Used by Analytics page for inventory trend charts

**Rationale**:
Using direct database operations instead of Eloquent for this table provides:
1. Faster bulk inserts (no model overhead)
2. Reduced memory usage for high-frequency snapshots
3. Optimized for write-heavy, read-light analytics workloads

**Example Query**:
```php
DB::table('inventory_history')
    ->where('user_id', $userId)
    ->where('location_id', $locationId)
    ->where('product_id', $productId)
    ->orderBy('day')
    ->get(['day', 'quantity']);
```

---

## Database Indexes

Key indexes for query performance:

```sql
-- Inventory lookups
CREATE INDEX idx_inventory_location_product ON inventories(location_id, product_id);
CREATE INDEX idx_inventory_expiry ON inventories(expiry_date) WHERE expiry_date IS NOT NULL;

-- Order queries
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_delivery_day ON orders(delivery_day);

-- Alert queries
CREATE INDEX idx_alerts_user_dismissed ON alerts(user_id, dismissed);
CREATE INDEX idx_alerts_severity ON alerts(severity) WHERE dismissed = false;

-- Spike events
CREATE INDEX idx_spike_events_active ON spike_events(active, start_day, end_day);
CREATE INDEX idx_spike_events_type ON spike_events(type);

-- Routes
CREATE INDEX idx_routes_origin_dest ON routes(origin_id, destination_id);
```

## Migration Strategy

Migrations are timestamped and executed in order. Key migrations:

1. **Core tables**: users, cache, jobs
2. **Game entities**: locations, vendors, products
3. **Inventory**: inventories, product_vendor pivot
4. **Orders**: orders, order_items, game_states
5. **Transfers**: transfers
6. **Events**: spike_events, alerts
7. **Logistics**: routes, shipments
8. **Enhancements**: Additional columns and indexes

## Data Seeding & Configuration

The application uses a "Single Source of Truth" approach for game data to ensure consistency between the Laravel backend and React frontend.

### Configuration File

**File**: `config/game_data.php`

This file defines:
- **Categories**: All 11 product categories (Beans, Milk, Cups, etc.)
- **Products**: Detailed definitions for all items (storage cost, shelf life, etc.)
- **Vendors**: Supplier definitions including reliability scores and category assignments

This configuration mirrors the frontend constants defined in `resources/js/constants.ts`.

### Seeding Process

The `CoreGameStateSeeder` reads from `config('game_data')` to populate the database, ensuring that:
1. All categories are represented
2. Product attributes match frontend expectations
3. Vendors are correctly linked to products they supply

## Related Documentation

- [Controllers & Routes](./03-controllers-routes.md)
- [Services](./04-services.md)
- [State Machines](./06-state-machines.md)
