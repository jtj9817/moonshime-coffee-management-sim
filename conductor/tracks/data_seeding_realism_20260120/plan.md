# Implementation Plan - Realistic Data Seeding

## Phase 1: Foundation & Configuration
- [ ] Task: Define Game Data Configuration
    - [ ] Create `config/game_data.php` containing the master list of 11 Categories and Products (mirroring `constants.ts`).
    - [ ] Verify structure matches `Product` model attributes.

- [ ] Task: Refactor LocationFactory
    - [ ] Update `database/factories/LocationFactory.php`.
    - [ ] Implement dynamic naming logic based on location `type` (Store, Hub, Vendor).
    - [ ] Add `unique()` constraint to Faker calls to prevent duplicates.

- [ ] Task: Conductor - User Manual Verification 'Phase 1' (Protocol in workflow.md)

## Phase 2: Seeder Implementation
- [ ] Task: Overhaul Product Seeding
    - [ ] Update `database/seeders/CoreGameStateSeeder.php`.
    - [ ] Remove hardcoded product creation.
    - [ ] Implement loop to read from `config/game_data.php` and create/update Products.

- [ ] Task: Implement Vendor & Inventory Logic
    - [ ] Update `CoreGameStateSeeder` (or create `InventorySeeder`) to assign Products to Vendors based on Category.
    - [ ] Implement logic to seed `Inventory` records: For each Store, for each Product, create record with `quantity > 50`.

- [ ] Task: Enhance Graph/Route Seeding
    - [ ] Update `database/seeders/GraphSeeder.php`.
    - [ ] Implement "Multi-hop" topology generation: Ensure `Vendor -> Hub -> Store` connections.
    - [ ] Logic to guarantee at least 3 valid paths per Distributor-Store pair.

- [ ] Task: Conductor - User Manual Verification 'Phase 2' (Protocol in workflow.md)

## Phase 3: Testing & Validation
- [ ] Task: Create Data Consistency Tests - Core Data
    - [ ] Create `tests/Feature/Seeder/DataConsistencyTest.php`.
    - [ ] Scenario 1: Verify exactly 11 Product Categories exist.
    - [ ] Scenario 2: Verify specific Item IDs exist as per config.
    - [ ] Scenario 3: Verify Location names are unique across all types.
    - [ ] Scenario 4: Verify Inventory levels are >= 50 for all product-store combinations.

- [ ] Task: Create Data Consistency Tests - Route Validity
    - [ ] Create `tests/Feature/Seeder/RouteConsistencyTest.php`.
    - [ ] Scenario 1: Verify direct Vendor -> Store routes (if applicable/allowed).
    - [ ] Scenario 2: Verify Multi-hop (Vendor -> Hub -> Store) connectivity exists.
    - [ ] Scenario 3: Verify at least 3 distinct paths exist for a sampled Vendor-Store pair.

- [ ] Task: Create Data Consistency Tests - Vendor/Product Logic
    - [ ] Create `tests/Feature/Seeder/VendorProductConsistencyTest.php`.
    - [ ] Scenario 1: Verify every Product has at least one assigned Vendor.
    - [ ] Scenario 2: Verify Vendors only sell products matching their assigned categories.
    - [ ] Scenario 3: Verify no orphaned products (products with 0 vendors).

- [ ] Task: Update Existing Test Suite - Data Integration
    - [ ] Analyze `tests/Feature/OrderTest.php`: Replace any manual factory creation of Products with retrieval of seeded "real" products to ensure order logic works with real data constraints.
    - [ ] Analyze `tests/Feature/InventoryTest.php`: Remove setup steps that artificially seed inventory, rely on the global seeder state, and verify operations against the >=50 baseline.
    - [ ] Analyze `tests/Feature/TransferTest.php`: Ensure source/destination locations used in tests are fetched from the new realistic set (e.g., "Seattle HQ") rather than created ad-hoc.

- [ ] Task: Conductor - User Manual Verification 'Phase 3' (Protocol in workflow.md)
