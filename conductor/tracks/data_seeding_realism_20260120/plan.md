# Implementation Plan - Realistic Data Seeding

## Phase 1: Foundation & Configuration
- [x] Task: Define Game Data Configuration
    - [x] Create `config/game_data.php` containing the master list of 11 Categories and Products (mirroring `constants.ts`).
    - [x] Verify structure matches `Product` model attributes.

- [x] Task: Refactor LocationFactory
    - [x] Update `database/factories/LocationFactory.php`.
    - [x] Implement dynamic naming logic based on location `type` (Store, Hub, Vendor).
    - [x] Add `unique()` constraint to Faker calls to prevent duplicates.

- [x] Task: Conductor - User Manual Verification 'Phase 1' (Protocol in workflow.md)

## Phase 2: Seeder Implementation
- [x] Task: Overhaul Product Seeding
    - [x] Update `database/seeders/CoreGameStateSeeder.php`.
    - [x] Remove hardcoded product creation.
    - [x] Implement loop to read from `config/game_data.php` and create/update Products.

- [x] Task: Implement Vendor & Inventory Logic
    - [x] Update `CoreGameStateSeeder` (or create `InventorySeeder`) to assign Products to Vendors based on Category.
    - [x] Implement logic to seed `Inventory` records: For each Store, for each Product, create record with `quantity > 50`.

- [x] Task: Enhance Graph/Route Seeding
    - [x] Update `database/seeders/GraphSeeder.php`.
    - [x] Implement "Multi-hop" topology generation: Ensure `Vendor -> Hub -> Store` connections.
    - [x] Logic to guarantee at least 3 valid paths per Distributor-Store pair.

- [x] Task: Conductor - User Manual Verification 'Phase 2' (Protocol in workflow.md)

## Phase 3: Testing & Validation
- [x] Task: Create Data Consistency Tests - Core Data
    - [x] Create `tests/Feature/Seeder/DataConsistencyTest.php`.
    - [x] Scenario 1: Verify exactly 11 Product Categories exist.
    - [x] Scenario 2: Verify specific Item IDs exist as per config.
    - [x] Scenario 3: Verify Location names are unique across all types.
    - [x] Scenario 4: Verify Inventory levels are >= 50 for all product-store combinations.

- [x] Task: Create Data Consistency Tests - Route Validity
    - [x] Create `tests/Feature/Seeder/RouteConsistencyTest.php`.
    - [x] Scenario 1: Verify direct Vendor -> Store routes (if applicable/allowed).
    - [x] Scenario 2: Verify Multi-hop (Vendor -> Hub -> Store) connectivity exists.
    - [x] Scenario 3: Verify at least 3 distinct paths exist for a sampled Vendor-Store pair.

- [x] Task: Create Data Consistency Tests - Vendor/Product Logic
    - [x] Create `tests/Feature/Seeder/VendorProductConsistencyTest.php`.
    - [x] Scenario 1: Verify every Product has at least one assigned Vendor.
    - [x] Scenario 2: Verify Vendors only sell products matching their assigned categories.
    - [x] Scenario 3: Verify no orphaned products (products with 0 vendors).

- [x] Task: Update Existing Test Suite - Data Integration
    - [x] Analyze `tests/Feature/OrderTest.php`: Replace any manual factory creation of Products with retrieval of seeded "real" products to ensure order logic works with real data constraints.
    - [x] Analyze `tests/Feature/InventoryTest.php`: Remove setup steps that artificially seed inventory, rely on the global seeder state, and verify operations against the >=50 baseline.
    - [x] Analyze `tests/Feature/TransferTest.php`: Ensure source/destination locations used in tests are fetched from the new realistic set (e.g., "Seattle HQ") rather than created ad-hoc.

- [x] Task: Conductor - User Manual Verification 'Phase 3' (Protocol in workflow.md)
