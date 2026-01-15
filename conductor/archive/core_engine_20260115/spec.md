# Specification: Core Inventory & Supply Chain Engine

## Goal
Implement the foundational data layer and business logic for the Moonshine Coffee Management Sim. This includes setting up the database schema for core entities (Locations, Products, Inventory, Vendors) and implementing the initial service layer for inventory calculations and replenishment.

## Core Models

### 1. Location (Stores)
- **Table:** `locations`
- **Attributes:** `id` (uuid), `name`, `address`, `max_storage`
- **Relationships:** `hasMany(Inventory)`, `hasMany(Order)`

### 2. Vendor (Suppliers)
- **Table:** `vendors`
- **Attributes:** `id` (uuid), `name`, `reliability_score`, `metrics` (json)
- **Relationships:** `hasMany(Order)`, `belongsToMany(Product)`

### 3. Product (Items)
- **Table:** `products`
- **Attributes:** `id` (uuid), `name`, `category`, `is_perishable`, `storage_cost`
- **Relationships:** `hasMany(Inventory)`

### 4. Inventory (Stock)
- **Table:** `inventories`
- **Attributes:** `id` (uuid), `location_id`, `product_id`, `quantity`, `last_restocked_at`
- **Relationships:** `belongsTo(Location)`, `belongsTo(Product)`
- **Observers:** `InventoryObserver` to monitor `updated` events and fire `LowStockDetected`.

## Core Services

### 1. InventoryMathService
- **Type:** Stateless Helper Service
- **Responsibilities:**
  - Calculate Economic Order Quantity (EOQ)
  - Calculate Safety Stock levels
  - Calculate Reorder Points
- **Logic:** Port existing logic from `resources/js/services/skuMath.ts` to PHP.

### 2. InventoryManagementService
- **Dependencies:** `InventoryMathService`, `RestockStrategyInterface`
- **Responsibilities:**
  - Handle `restock` operations (transactional)
  - Handle `consume` operations (transactional)
  - Handle `waste` operations (transactional)
- **Pattern:** Strategy Pattern for determining reorder amounts based on active Policy.

## Events
- `LowStockDetected`: Fired by `InventoryObserver` when quantity drops below threshold.

## Deliverables
- Migration files for all 4 core tables.
- Eloquent Models with defined relationships and casts.
- `InventoryObserver` implementation.
- `InventoryMathService` with unit tests covering math logic.
- `InventoryManagementService` with basic strategies.
- Unit and Feature tests for all new components.