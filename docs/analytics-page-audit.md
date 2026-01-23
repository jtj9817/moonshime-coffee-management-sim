# Analytics Page Data Source Audit

**Date**: 2026-01-23
**Status**: Comprehensive Feature Map Complete
**Scope**: `/game/analytics` route, components, backend data providers, and database schema

---

## Executive Summary

The Analytics page uses a **hybrid data approach**:

| Data Source | Real Data | Notes |
|---|---|---|
| **Location Comparison** | ✓ Yes | Fetches actual inventory values from database |
| **Spending by Category** | ⚠ Partial | Real categories from products table, but random amounts |
| **Inventory Trends** | ✗ No | Hard-coded placeholder values |

**Key Finding**: The analytics page displays a mix of real and simulated data. While location data is accurately calculated from the database, spending and trend data are not representative of actual game state.

---

## Full Feature Map: Analytics Dashboard

### Layer 1: Database Schema

#### Core Tables for Analytics

| Table | Purpose | Analytics Usage |
|-------|---------|-----------------|
| `daily_reports` | Per-day game snapshots | Inventory trends, historical metrics |
| `inventories` | Current SKU inventory levels | Location comparison, stock levels |
| `products` | Product definitions | Categories, storage costs, pricing |
| `orders` | Purchase orders | Spending by category, cash flow |
| `order_items` | Order line items | Detailed spending breakdown |
| `locations` | Physical locations | Location comparison metrics |
| `game_states` | Current game state | Day counter, cash, XP |
| `spike_events` | Demand spikes | Correlation with inventory changes |
| `alerts` | System alerts | Operational health metrics |

#### Table Schemas

**`daily_reports` Table**
```sql
CREATE TABLE daily_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    day INT NOT NULL,
    summary_data JSON NULL,
    metrics JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (user_id, day),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
- `summary_data`: Contains daily counts (orders_placed, spikes_started, spikes_ended, alerts_generated, transfers_completed)
- `metrics`: Contains state snapshots (cash, xp)

**`inventories` Table**
```sql
CREATE TABLE inventories (
    id CHAR(36) PRIMARY KEY,
    location_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    quantity INT DEFAULT 0,
    last_restocked_at TIMESTAMP NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (user_id, location_id, product_id),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**`products` Table**
```sql
CREATE TABLE products (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    category VARCHAR(255),
    is_perishable BOOLEAN DEFAULT FALSE,
    storage_cost DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**`orders` Table**
```sql
CREATE TABLE orders (
    id CHAR(36) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    vendor_id CHAR(36) NOT NULL,
    location_id CHAR(36) NULL,
    route_id CHAR(36) NULL,
    status VARCHAR(255),
    total_cost INT,
    delivery_date TIMESTAMP NULL,
    delivery_day INT NULL,
    created_day INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
    INDEX (created_day)
);
```

**`order_items` Table**
```sql
CREATE TABLE order_items (
    id CHAR(36) PRIMARY KEY,
    order_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    quantity INT,
    cost_per_unit INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

**`locations` Table**
```sql
CREATE TABLE locations (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    address VARCHAR(255),
    max_storage INT,
    type VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**`game_states` Table**
```sql
CREATE TABLE game_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL,
    cash BIGINT DEFAULT 1000000,  -- stored in cents ($10,000.00)
    xp INT DEFAULT 0,
    day INT DEFAULT 1,
    spike_cooldowns JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Database Relationships

```
Users (1) ← → (Many) DailyReports
Users (1) ← → (Many) Inventories
Users (1) ← → (Many) Orders
Users (1) ← → (1) GameState

Locations (1) ← → (Many) Inventories
Locations (1) ← → (Many) Orders (as target)

Products (1) ← → (Many) Inventories
Products (1) ← → (Many) OrderItems

Orders (1) ← → (Many) OrderItems
Orders (Many) ← → (1) Vendors
```

---

### Layer 2: Backend Services

#### Controller: `GameController.php`

**Route**: `GET /game/analytics` (line 23 in `routes/web.php`)

```php
public function analytics(): Response
{
    return Inertia::render('game/analytics', [
        'inventoryTrends' => $this->getInventoryTrends(),
        'spendingByCategory' => $this->getSpendingByCategory(),
        'locationComparison' => $this->getLocationComparison(),
    ]);
}
```

#### Data Provider Methods

**`getInventoryTrends()` (lines 487-494)**
```php
protected function getInventoryTrends(): array
{
    return [
        ['day' => 1, 'value' => 1000],
        ['day' => 2, 'value' => 950],
        ['day' => 3, 'value' => 1100],
    ];
}
```
**Status**: ❌ Hard-coded - Returns static 3-day values

**`getSpendingByCategory()` (lines 499-506)**
```php
protected function getSpendingByCategory(): array
{
    return Product::select('category')
        ->distinct()
        ->pluck('category')
        ->map(fn ($cat) => ['category' => $cat, 'amount' => rand(1000, 5000)])
        ->toArray();
}
```
**Status**: ⚠ Partial - Real categories, but `rand()` for amounts

**`getLocationComparison()` (lines 511-520)**
```php
protected function getLocationComparison(): array
{
    return Location::all()->map(fn ($loc) => [
        'name' => $loc->name,
        'inventoryValue' => Inventory::where('location_id', $loc->id)
            ->with('product')
            ->get()
            ->sum(fn ($inv) => $inv->quantity * ($inv->product->storage_cost ?? 0)),
    ])->toArray();
}
```
**Status**: ✓ Real Data - Queries actual inventory and products

#### Event Listeners

**`CreateDailyReport` Listener** (`app/Listeners/CreateDailyReport.php`)

Triggered by `TimeAdvanced` event, generates reports for the previous day:

```php
$summary = [
    'orders_placed' => Order::where('user_id', $user->id)
        ->where('created_day', $previousDay)
        ->count(),
    'spikes_started' => SpikeEvent::where('user_id', $user->id)
        ->where('starts_at_day', $previousDay)
        ->count(),
    'spikes_ended' => SpikeEvent::where('user_id', $user->id)
        ->where('ends_at_day', $previousDay)
        ->count(),
    'alerts_generated' => Alert::where('user_id', $user->id)
        ->where('created_day', $previousDay)
        ->count(),
    'transfers_completed' => Transfer::where('user_id', $user->id)
        ->where('delivery_day', $previousDay)
        ->count(),
];
```

---

### Layer 3: Frontend Architecture

#### Page Component: `resources/js/pages/game/analytics.tsx`

**Props Interface**:
```typescript
interface AnalyticsProps {
    inventoryTrends: Array<{ day: number; value: number }>;
    spendingByCategory: Array<{ category: string; amount: number }>;
    locationComparison: Array<{ name: string; inventoryValue: number }>;
}
```

**Component Structure**:
```
Analytics Page
├── GameLayout (with breadcrumbs)
├── Header Section
│   ├── Title: "Analytics Dashboard"
│   └── Subtitle: "Insights and performance metrics"
├── Summary Cards (3 columns)
│   ├── Total Inventory Value (calculated from locationComparison)
│   ├── Total Spending (calculated from spendingByCategory)
│   └── Categories Tracked (length of spendingByCategory)
└── Charts Grid (2 columns)
    ├── Inventory Trends (Bar Chart)
    │   └── Vertical bars representing daily values
    ├── Spending by Category (Progress Bars)
    │   └── Horizontal progress bars with percentage widths
    └── Location Comparison (Grid, full width)
        └── Cards showing inventory value per location
```

**Data Flow**:
1. Server renders `GameController::analytics()` → Inertia passes props
2. React page receives `inventoryTrends`, `spendingByCategory`, `locationComparison`
3. Component derives:
   - `totalInventoryValue` = `locationInventoryValues` summed
   - `totalSpending` = `spendingByCategory` amounts summed
4. Charts render using derived values

#### Orphaned Component: `resources/js/components/Analytics.tsx`

**Status**: Not currently used in routing

**Features**:
- Date range picker with state management
- Demand vs Forecast area chart (using Recharts)
- Storage Utilization bar chart (using Recharts)
- Synthetic data generation with seeded random numbers
- Weekly insight alert box

**Why Orphaned**: Newer implementation (`analytics.tsx`) page uses custom chart rendering instead of Recharts

---

### Layer 4: Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER REQUEST                                │
│                      GET /game/analytics                            │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    LARAVEL ROUTE (web.php)                         │
│              Route::get('/analytics', GameController...)            │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   GAMECONTROLLER::analytics()                       │
│                                                                      │
│  ┌────────────────────┐  ┌──────────────────────┐  ┌─────────────┐ │
│  │ getInventoryTrends │  │ getSpendingByCategory │  │ getLocation  │ │
│  │   (Hard-coded)     │  │    (Partial)          │  │ Comparison  │ │
│  └────────────────────┘  └──────────────────────┘  │  (Real)     │ │
│                                                            └───────┘ │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     DATABASE QUERIES                               │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  SELECT DISTINCT category FROM products                       │ │
│  └──────────────────────────────────────────────────────────────┘ │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  SELECT * FROM locations                                      │ │
│  │  JOIN inventories ON location_id                             │ │
│  │  JOIN products ON product_id                                  │ │
│  │  SUM(quantity * storage_cost) AS value GROUP BY location_id  │ │
│  └──────────────────────────────────────────────────────────────┘ │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     INERTIA RESPONSE                               │
│                    Render 'game/analytics'                          │
│                    Pass props: inventoryTrends,                     │
│                    spendingByCategory, locationComparison          │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   REACT PAGE COMPONENT                              │
│              resources/js/pages/game/analytics.tsx                  │
│                                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────────┐ │
│  │ Summary     │  │ Bar Charts  │  │ Location Comparison Cards   │ │
│  │ Cards       │  │ Inventory   │  │ (Derived Calculations)      │ │
│  └─────────────┘  │ Trends      │  └─────────────────────────────┘ │
│                   └─────────────┘                                     │
└────────────────────────┬────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      RENDERED UI                                    │
│                    Analytics Dashboard                              │
└─────────────────────────────────────────────────────────────────────┘
```

---

---

## Data Source Status Matrix

| Metric | Data Source | Table(s) | Status | Method | Notes |
|--------|-------------|----------|--------|--------|-------|
| **Inventory Trends** | Hard-coded | N/A | ❌ Fake | `getInventoryTrends()` | Returns static 3-day values |
| **Spending by Category** | Partial | `products` | ⚠ Hybrid | `getSpendingByCategory()` | Real categories, `rand()` amounts |
| **Location Comparison** | Real | `inventories`, `products`, `locations` | ✓ Real | `getLocationComparison()` | Calculates value from DB |

### Potential Data Sources for Enhancement

| Enhancement | Data Source | Implementation Path |
|-------------|-------------|-------------------|
| Real Inventory Trends | `daily_reports.metrics` | Query `metrics` JSON for historical cash/xp |
| | `inventories` with timestamps | Create history table or snapshot inventories |
| Real Spending by Category | `orders` + `order_items` + `products` | JOIN and GROUP BY product.category |
| Storage Utilization | `inventories.quantity` + `locations.max_storage` | Calculate % of capacity per location |
| Waste Tracking | (Would need `waste_events` table) | Track perished items and losses |
| Order Fulfillment Rate | `orders.status` | Compare delivered vs pending orders |
| Spike Impact Analysis | `spike_events` + `inventories` | Correlate spikes with inventory changes |

---

## Model Relationships Used in Analytics

### Models with Relevant Methods

**DailyReport** (`app/Models/DailyReport.php`)
```php
// Properties
- user_id: BelongsTo User
- day: integer
- summary_data: JSON (orders_placed, spikes_started, spikes_ended, etc.)
- metrics: JSON (cash, xp)
```

**Inventory** (`app/Models/Inventory.php`)
```php
// Relationships
- user(): BelongsTo User
- location(): BelongsTo Location
- product(): BelongsTo Product

// Properties
- quantity: integer
- last_restocked_at: datetime
```

**Product** (`app/Models/Product.php`)
```php
// Relationships
- inventories(): HasMany Inventory
- vendors(): BelongsToMany Vendor

// Properties
- category: string
- storage_cost: decimal
- is_perishable: boolean
```

**Order** (`app/Models/Order.php`)
```php
// Relationships
- user(): BelongsTo User
- vendor(): BelongsTo Vendor
- location(): BelongsTo Location
- items(): HasMany OrderItem

// Properties
- total_cost: integer (stored in cents)
- created_day: integer
- delivery_day: integer
- status: OrderState
```

**OrderItem** (`app/Models/OrderItem.php`)
```php
// Relationships
- order(): BelongsTo Order
- product(): BelongsTo Product

// Properties
- quantity: integer
- cost_per_unit: float
```

**Location** (`app/Models/Location.php`)
```php
// Relationships
- inventories(): HasMany Inventory
- outgoingRoutes(): HasMany Route (as source)
- incomingRoutes(): HasMany Route (as target)

// Properties
- max_storage: integer
- type: string
```

**GameState** (`app/Models/GameState.php`)
```php
// Relationships
- user(): BelongsTo User

// Properties
- cash: float (in dollars, derived from cents storage)
- xp: integer
- day: integer
- spike_cooldowns: JSON
```

---

## Issues & Gaps

### High Priority

1. **Inventory Trends are Hard-coded**
   - Only 3 days of data
   - No connection to actual game state
   - Should reflect inventory levels across all locations over time
   - **Impact**: Users see meaningless chart data

2. **Spending by Category uses Random Values**
   - Categories are real but amounts are `rand(1000, 5000)`
   - Doesn't represent actual order history
   - **Impact**: Spending insights are unreliable

### Medium Priority

3. **Orphaned Analytics Component**
   - `resources/js/components/Analytics.tsx` is not used
   - Contains synthetic data generation logic
   - Should either be deleted or documented

4. **Storage Cost vs Unit Price**
   - Location Comparison uses `storage_cost` field
   - May not represent actual inventory value
   - Consider clarifying intent or switching to `unit_price` × quantity

### Low Priority

5. **No Date Range Filtering**
   - Page shows all data without time period selection
   - The unused `Analytics.tsx` component has date range UI
   - Could be useful for trending over game progression

---

## Detailed Implementation Proposals

### Phase 1: Fix Core Data Issues

#### 1. Update `getInventoryTrends()` - Option A: Using DailyReport

```php
protected function getInventoryTrends(): array
{
    $userId = auth()->id();
    $gameState = GameState::where('user_id', $userId)->first();
    $currentDay = $gameState ? $gameState->day : 1;

    $reports = DailyReport::where('user_id', $userId)
        ->where('day', '>=', 1)
        ->where('day', '<=', $currentDay)
        ->orderBy('day')
        ->get();

    return $reports->map(function ($report) {
        return [
            'day' => $report->day,
            'value' => $report->metrics['cash'] ?? 0,
            'xp' => $report->metrics['xp'] ?? 0,
        ];
    })->toArray();
}
```

#### 2. Update `getInventoryTrends()` - Option B: Using Inventory Snapshots

**Would require new table** (`inventory_history`):

```php
protected function getInventoryTrends(): array
{
    $userId = auth()->id();
    $gameState = GameState::where('user_id', $userId)->first();
    $currentDay = $gameState ? $gameState->day : 1;

    $trends = InventoryHistory::where('user_id', $userId)
        ->where('day', '>=', max(1, $currentDay - 30)) // Last 30 days
        ->selectRaw('day, SUM(quantity * storage_cost) as total_value')
        ->groupBy('day')
        ->orderBy('day')
        ->get();

    return $trends->map(fn ($trend) => [
        'day' => $trend->day,
        'value' => $trend->total_value,
    ])->toArray();
}
```

#### 3. Update `getSpendingByCategory()`

```php
protected function getSpendingByCategory(): array
{
    $userId = auth()->id();

    $spending = Order::where('orders.user_id', $userId)
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->selectRaw('
            products.category,
            SUM(order_items.quantity * order_items.cost_per_unit) as total_amount
        ')
        ->groupBy('products.category')
        ->orderByDesc('total_amount')
        ->get();

    $totalSpending = $spending->sum('total_amount');

    return $spending->map(function ($item) use ($totalSpending) {
        return [
            'category' => $item->category,
            'amount' => (int) $item->total_amount, // stored in cents
            'percentage' => $totalSpending > 0
                ? round(($item->total_amount / $totalSpending) * 100, 1)
                : 0,
        ];
    })->toArray();
}
```

#### 4. Update `getLocationComparison()` with Enhanced Data

```php
protected function getLocationComparison(): array
{
    $userId = auth()->id();

    return Location::all()->map(function ($location) use ($userId) {
        $inventories = Inventory::where('location_id', $location->id)
            ->where('user_id', $userId)
            ->with('product')
            ->get();

        $inventoryValue = $inventories->sum(function ($inv) {
            return $inv->quantity * ($inv->product->storage_cost ?? 0);
        });

        $totalQuantity = $inventories->sum('quantity');
        $utilizationPercent = $location->max_storage > 0
            ? round(($totalQuantity / $location->max_storage) * 100, 1)
            : 0;

        return [
            'name' => $location->name,
            'inventoryValue' => $inventoryValue,
            'itemCount' => $inventories->count(),
            'totalQuantity' => $totalQuantity,
            'utilizationPercent' => $utilizationPercent,
            'maxStorage' => $location->max_storage,
            'type' => $location->type,
        ];
    })->toArray();
}
```

### Phase 2: New Analytics Metrics

#### Add `getStorageUtilization()` Method

```php
protected function getStorageUtilization(): array
{
    $userId = auth()->id();

    return Location::with(['inventories' => function ($query) use ($userId) {
        $query->where('user_id', $userId);
    }])->get()->map(function ($location) {
        $totalQuantity = $location->inventories->sum('quantity');
        $utilizationPercent = $location->max_storage > 0
            ? round(($totalQuantity / $location->max_storage) * 100, 1)
            : 0;

        return [
            'name' => $location->name,
            'used' => $totalQuantity,
            'max' => $location->max_storage,
            'utilizationPercent' => $utilizationPercent,
        ];
    })->toArray();
}
```

#### Add `getOrderFulfillmentMetrics()` Method

```php
protected function getOrderFulfillmentMetrics(): array
{
    $userId = auth()->id();

    $totalOrders = Order::where('user_id', $userId)->count();
    $deliveredOrders = Order::where('user_id', $userId)
        ->where('status', 'delivered')
        ->count();
    $pendingOrders = Order::where('user_id', $userId)
        ->where('status', 'pending')
        ->count();

    $avgDeliveryTime = Order::where('user_id', $userId)
        ->where('status', 'delivered')
        ->selectRaw('AVG(delivery_day - created_day) as avg_days')
        ->value('avg_days') ?? 0;

    return [
        'totalOrders' => $totalOrders,
        'deliveredOrders' => $deliveredOrders,
        'pendingOrders' => $pendingOrders,
        'fulfillmentRate' => $totalOrders > 0
            ? round(($deliveredOrders / $totalOrders) * 100, 1)
            : 0,
        'avgDeliveryTimeDays' => round($avgDeliveryTime, 1),
    ];
}
```

#### Add `getSpikeImpactAnalysis()` Method

```php
protected function getSpikeImpactAnalysis(): array
{
    $userId = auth()->id();

    return SpikeEvent::where('user_id', $userId)
        ->with(['location', 'product'])
        ->orderBy('starts_at_day', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($spike) use ($userId) {
            // Get inventory level at spike start
            $inventoryAtStart = Inventory::where('user_id', $userId)
                ->where('location_id', $spike->location_id)
                ->where('product_id', $spike->product_id)
                ->first();

            return [
                'id' => $spike->id,
                'day' => $spike->starts_at_day,
                'location' => $spike->location->name,
                'product' => $spike->product->name,
                'inventoryLevel' => $inventoryAtStart ? $inventoryAtStart->quantity : 0,
                'demandIncreasePercent' => $spike->demand_increase_percent ?? 0,
                'isResolved' => !$spike->is_active,
                'resolvedBy' => $spike->resolved_by,
            ];
        })->toArray();
}
```

### Phase 3: Enhanced TypeScript Interfaces

```typescript
interface EnhancedAnalyticsProps {
    // Existing metrics
    inventoryTrends: Array<{
        day: number;
        value: number;
        xp?: number;
        cash?: number;
    }>;
    spendingByCategory: Array<{
        category: string;
        amount: number; // in cents
        percentage: number;
        orderCount?: number;
    }>;
    locationComparison: Array<{
        name: string;
        inventoryValue: number;
        itemCount: number;
        totalQuantity: number;
        utilizationPercent: number;
        maxStorage: number;
        type: string;
    }>;

    // New metrics
    storageUtilization?: Array<{
        name: string;
        used: number;
        max: number;
        utilizationPercent: number;
    }>;
    orderFulfillment?: {
        totalOrders: number;
        deliveredOrders: number;
        pendingOrders: number;
        fulfillmentRate: number;
        avgDeliveryTimeDays: number;
    };
    spikeImpact?: Array<{
        id: string;
        day: number;
        location: string;
        product: string;
        inventoryLevel: number;
        demandIncreasePercent: number;
        isResolved: boolean;
        resolvedBy?: string;
    }>;

    // Filters
    dateRange?: {
        start: string;
        end: string;
    };
    locationFilter?: string;
    categoryFilter?: string;
}
```

### Phase 4: Database Schema Additions

#### New Table: `inventory_history`

```sql
CREATE TABLE inventory_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    location_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    day INT NOT NULL,
    quantity INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE (user_id, location_id, product_id, day),
    INDEX (user_id, day),
    INDEX (location_id, day)
);
```

#### Add to `products` Table

```sql
ALTER TABLE products ADD COLUMN unit_price DECIMAL(8,2) DEFAULT 0.00 AFTER storage_cost;
ALTER TABLE products ADD INDEX (category);
```

---

## SQL Query Examples for Analytics

### Query 1: Total Spending by Category

```sql
SELECT
    p.category,
    SUM(oi.quantity * oi.cost_per_unit) as total_spending_cents,
    COUNT(DISTINCT o.id) as order_count
FROM orders o
INNER JOIN order_items oi ON o.id = oi.order_id
INNER JOIN products p ON oi.product_id = p.id
WHERE o.user_id = ?
GROUP BY p.category
ORDER BY total_spending_cents DESC;
```

### Query 2: Inventory Value by Location

```sql
SELECT
    l.name as location_name,
    l.type,
    SUM(i.quantity * p.storage_cost) as inventory_value_cents,
    SUM(i.quantity) as total_quantity,
    l.max_storage,
    ROUND((SUM(i.quantity) / l.max_storage) * 100, 1) as utilization_percent
FROM locations l
LEFT JOIN inventories i ON l.id = i.location_id AND i.user_id = ?
LEFT JOIN products p ON i.product_id = p.id
GROUP BY l.id, l.name, l.type, l.max_storage
ORDER BY inventory_value_cents DESC;
```

### Query 3: Historical Cash/XP Trends

```sql
SELECT
    day,
    metrics->>'$.cash' as cash_cents,
    metrics->>'$.xp' as xp,
    summary_data->>'$.orders_placed' as orders_placed,
    summary_data->>'$.spikes_started' as spikes_started
FROM daily_reports
WHERE user_id = ?
ORDER BY day ASC;
```

### Query 4: Order Fulfillment Metrics

```sql
SELECT
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    AVG(CASE WHEN status = 'delivered' THEN delivery_day - created_day END) as avg_delivery_days,
    ROUND(
        (SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)),
        1
    ) as fulfillment_rate_percent
FROM orders
WHERE user_id = ?;
```

### Query 5: Spike Impact on Inventory

```sql
SELECT
    se.id as spike_id,
    se.starts_at_day,
    l.name as location_name,
    p.name as product_name,
    i.quantity as current_inventory,
    se.demand_increase_percent,
    se.is_active,
    se.resolved_by
FROM spike_events se
LEFT JOIN locations l ON se.location_id = l.id
LEFT JOIN products p ON se.product_id = p.id
LEFT JOIN inventories i ON se.location_id = i.location_id
    AND se.product_id = i.product_id
    AND se.user_id = i.user_id
WHERE se.user_id = ?
ORDER BY se.starts_at_day DESC
LIMIT 20;
```

---

## Frontend Component Enhancements

### New Summary Cards Component

```typescript
interface AnalyticsSummaryProps {
    inventoryValue: number;
    totalSpending: number;
    categoriesTracked: number;
    fulfillmentRate?: number;
    avgDeliveryDays?: number;
}

export function AnalyticsSummaryCards({
    inventoryValue,
    totalSpending,
    categoriesTracked,
    fulfillmentRate,
    avgDeliveryDays,
}: AnalyticsSummaryProps) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {/* Existing cards */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm font-medium">Inventory Value</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">
                        ${formatCurrency(inventoryValue)}
                    </div>
                </CardContent>
            </Card>

            {/* Add fulfillment rate if available */}
            {fulfillmentRate !== undefined && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium">Fulfillment Rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{fulfillmentRate}%</div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
```

---

## Testing Strategy

### Backend Tests

```php
// tests/Feature/AnalyticsTest.php

test('inventory trends uses actual daily reports', function () {
    $user = User::factory()->create();
    actingAs($user);

    // Create game state
    GameState::create([
        'user_id' => $user->id,
        'day' => 5,
        'cash' => 500000,
    ]);

    // Create daily reports
    DailyReport::factory()->for($user)->create([
        'day' => 1,
        'metrics' => ['cash' => 100000, 'xp' => 50],
    ]);
    DailyReport::factory()->for($user)->create([
        'day' => 2,
        'metrics' => ['cash' => 95000, 'xp' => 100],
    ]);

    $response = $this->get(route('game.analytics'));

    $response->assertInertia(function ($page) {
        expect($page->props('inventoryTrends'))->toHaveCount(2);
        expect($page->props('inventoryTrends')[0]['day'])->toBe(1);
        expect($page->props('inventoryTrends')[0]['value'])->toBe(100000);
    });
});

test('spending by category uses actual orders', function () {
    $user = User::factory()->create();
    actingAs($user);

    // Create products
    $milk = Product::factory()->create(['category' => 'Dairy']);
    $beans = Product::factory()->create(['category' => 'Coffee']);

    // Create orders
    $order = Order::factory()->for($user)->create([
        'total_cost' => 50000,
    ]);
    OrderItem::factory()->for($order)->for($milk)->create([
        'quantity' => 10,
        'cost_per_unit' => 200,
    ]);

    $response = $this->get(route('game.analytics'));

    $response->assertInertia(function ($page) {
        $spending = $page->props('spendingByCategory');
        expect($spending)->toHaveCount(1);
        expect($spending[0]['category'])->toBe('Dairy');
        expect($spending[0]['amount'])->toBe(2000); // 10 * 200 cents
    });
});
```

---

## Performance Considerations

### Current Query Performance Issues

1. **N+1 Query Problem in `getLocationComparison()`**
   - Calls `Inventory::where('location_id', $loc->id)` for each location
   - Solution: Eager load or use a single aggregated query

   ```php
   // Optimized version
   protected function getLocationComparison(): array
   {
       $userId = auth()->id();

       $inventoryByLocation = Inventory::where('user_id', $userId)
           ->with('product')
           ->get()
           ->groupBy('location_id');

       return Location::all()->map(function ($location) use ($inventoryByLocation) {
           $inventories = $inventoryByLocation->get($location->id, collect());
           // ... rest of logic
       })->toArray();
   }
   ```

2. **Missing Database Indexes**
   - `orders.created_day` - Added ✅
   - `products.category` - Needs to be added
   - `daily_reports.user_id` - Needs composite index with `day`

   ```sql
   CREATE INDEX idx_products_category ON products(category);
   CREATE INDEX idx_daily_reports_user_day ON daily_reports(user_id, day);
   ```

3. **Potential Caching Strategy**
   ```php
   protected function getSpendingByCategory(): array
   {
       return Cache::remember("analytics.spending.{$userId}", 3600, function () {
           // ... query logic
       });
   }
   ```

---

## Recommendations Summary

### Phase 1: Fix Core Data Issues (Priority: HIGH)

1. **Update `getInventoryTrends()`** - Query `DailyReport.metrics` for historical data
2. **Update `getSpendingByCategory()`** - JOIN Orders and OrderItems, sum actual costs
3. **Add database indexes** on `products.category` and `daily_reports(user_id, day)`

### Phase 2: Enhance Analytics (Priority: MEDIUM)

1. Add `getStorageUtilization()` method
2. Add `getOrderFulfillmentMetrics()` method
3. Add `getSpikeImpactAnalysis()` method
4. Create `inventory_history` table for historical snapshots
5. Add `unit_price` column to `products` table

### Phase 3: UI/UX Improvements (Priority: MEDIUM)

1. Implement date range filtering
2. Add interactive charts with tooltips
3. Add drill-down capability on summary cards
4. Export analytics data as CSV/PDF

### Phase 4: Cleanup (Priority: LOW)

1. Remove or document `resources/js/components/Analytics.tsx`
2. Add comprehensive test suite
3. Document data source assumptions
4. Add API documentation

---

## Code References

| File | Lines | Purpose |
|---|---|---|
| `app/Http/Controllers/GameController.php` | 192-199 | Analytics route handler |
| `app/Http/Controllers/GameController.php` | 487-520 | Data provider methods |
| `resources/js/pages/game/analytics.tsx` | 1-173 | Main page component |
| `resources/js/components/Analytics.tsx` | 1-146 | Unused legacy component |

---

## Database Relationships

```
Location (1) ← → (Many) Inventory
Product (1) ← → (Many) Inventory
Product (1) ← → (Many) OrderItem
Order (1) ← → (Many) OrderItem
Order (Many) ← → (1) Vendor
```

**For Analytics Enhancement**:
- Use `Inventory` → `Product` → `Orders` join to calculate spending
- Use daily snapshots from `DailyReport` for trends
- Correlate with `SpikeEvent` for demand patterns

---

## Implementation Notes

- All data flows through Inertia server-side rendering (no REST API calls)
- No real-time updates (page refresh required for current data)
- Analytics are scoped to current authenticated user (via `auth()->id()` in models)
- No caching observed; queries execute on each page load

---

## Appendix: Data Type Interfaces

### Current Props Interface

```typescript
interface AnalyticsProps {
    inventoryTrends: Array<{ day: number; value: number }>;
    spendingByCategory: Array<{ category: string; amount: number }>;
    locationComparison: Array<{ name: string; inventoryValue: number }>;
}
```

### Suggested Enhanced Props

```typescript
interface AnalyticsProps {
    inventoryTrends: Array<{
        day: number;
        value: number;
        timestamp?: string;
    }>;
    spendingByCategory: Array<{
        category: string;
        amount: number;
        percentage?: number;
    }>;
    locationComparison: Array<{
        name: string;
        inventoryValue: number;
        itemCount?: number;
    }>;
    dateRange?: { start: string; end: string };
}
```

