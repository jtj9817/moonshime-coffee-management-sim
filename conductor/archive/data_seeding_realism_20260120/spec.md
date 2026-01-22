# Specification: Realistic Data Seeding & Synchronization

## 1. Overview
This track aims to resolve critical data inconsistencies between the frontend and backend by overhauling the database seeding strategy. The goal is to generate realistic, diverse, and robust dataset that supports the game's complexity, ensuring 1:1 parity with frontend expectations (11 product categories) and providing a rich test environment.

## 2. Functional Requirements

### 2.1 Single Source of Truth (Products & Categories)
- **Mechanism:** Implement a "Backend Master" approach.
- **Action:** Create a centralized configuration file (e.g., `config/game_data.php`) that defines all 11 product categories and their specific products (matching the current `constants.ts` values).
- **Seeding:** Update `CoreGameStateSeeder` to iterate through this configuration to create `Product` records, ensuring all categories and items are present.
- **Constraint:** Ensure strict consistency: 11 Categories, specific Item IDs (e.g., `item-1`), and attributes (perishability, cost).

### 2.2 Realistic Location Naming
- **Strategy:** Faker-Driven with contextual logic.
- **Implementation:** Update `LocationFactory` to generate names dynamically based on `type`.
  - **Stores:** `[Faker Company] Coffee`, `[Faker City] Cafe`
  - **Warehouses:** `[Faker City] Distribution Center`, `[Faker City] Depot`
  - **Vendors:** `[Faker Company] Wholesale`, `[Faker Last Name] Imports`
- **Constraint:** Ensure uniqueness to prevent duplicate "Test Coffee" entries.

### 2.3 Initial Inventory Seeding
- **Requirement:** Every store must start with significant stock.
- **Logic:** In `CoreGameStateSeeder` (or a dedicated `InventorySeeder`), iterate through every created Store and every Product.
- **Action:** Create `Inventory` records with a random quantity `min: 50` for each combination.
- **Constraint:** No store should have 0 stock of any core item.

### 2.4 Supply Chain Topology (Routes)
- **Requirement:** Robust multi-hop connectivity.
- **Logic:** Ensure every Store is connected to every Vendor type via the logistics graph.
- **Implementation:**
  - Create `Vendor` -> `Hub` edges.
  - Create `Hub` -> `Store` edges.
  - **Validation:** Ensure at least 3 distinct valid paths exist for a "Distributor -> Store" flow (i.e., multiple Hub options or direct + hub routes).

### 2.5 Distributor-Product Logic
- **Requirement:** Specific vendors sell specific products.
- **Logic:** Assign product categories to vendors logically (e.g., "Bean Baron" gets all Beans, "Dairy King" gets Milk).
- **Constraint:** Ensure every product type has at least one valid distributor.

## 3. Testing & Verification

### 3.1 New Test Suites
- **`tests/Feature/Seeder/DataConsistencyTest.php`:**
  - Verify 11 Product Categories exist.
  - Verify all specific Item IDs exist.
  - Verify Location names are unique.
  - Verify Inventory levels are >= 50 for all stores.

### 3.2 Update Existing Tests
- Refactor `tests/Feature/OrderTest.php`, `tests/Feature/InventoryTest.php`, etc., to utilize the new realistic seeder data where applicable, or update their local factories to match the new schema constraints.

## 4. Non-Functional Requirements
- **Performance:** Seeding should remain fast (< 5 seconds for local dev).
- **Maintainability:** The central config file should be easy to read and update.

## 5. Out of Scope
- Automated generation of `constants.ts` from the PHP config (this is a future task).
- modifying the Frontend codebase (React/TS).
