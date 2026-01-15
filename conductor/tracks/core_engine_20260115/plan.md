# Implementation Plan - Core Inventory & Supply Chain Engine

## Phase 1: Database Schema & Core Models [checkpoint: 522c8be]
- [x] Task: Create Migrations for Core Entities
    - [x] Create migration for `locations` table
    - [x] Create migration for `vendors` table
    - [x] Create migration for `products` table
    - [x] Create migration for `inventories` table (with foreign keys)
    - [x] Run migrations to verify schema
- [x] Task: Implement Eloquent Models
    - [x] Create `Location` model with relationships
    - [x] Create `Vendor` model with relationships
    - [x] Create `Product` model with relationships
    - [x] Create `Inventory` model with relationships
- [x] Task: Create Model Factories and Seeders
    - [x] Create Factories for all 4 models
    - [x] Create Seeder to populate initial game state
- [x] Task: Implement InventoryObserver
    - [x] Create `LowStockDetected` event
    - [x] Create `InventoryObserver` to watch `updated` event
    - [x] Register observer in `AppServiceProvider`
- [x] Task: Conductor - User Manual Verification 'Phase 1: Database Schema & Core Models' (Protocol in workflow.md)

## Phase 2: Core Services & Math Logic
- [ ] Task: Implement InventoryMathService
    - [ ] Create `InventoryMathService` class
    - [ ] Port logic from `skuMath.ts` (EOQ, Safety Stock)
    - [ ] Write Unit Tests for calculation accuracy
- [ ] Task: Define Strategy Interfaces
    - [ ] Create `RestockStrategyInterface`
    - [ ] Implement `JustInTimeStrategy`
    - [ ] Implement `SafetyStockStrategy`
- [ ] Task: Implement InventoryManagementService
    - [ ] Create `InventoryManagementService` class
    - [ ] Implement `restock` method with transaction support
    - [ ] Implement `consume` method with transaction support
    - [ ] Implement `waste` method with transaction support
    - [ ] Integrate `RestockStrategyInterface` into logic
- [ ] Task: Register Services in Container
    - [ ] Create `GameServiceProvider`
    - [ ] Bind interfaces and services
- [ ] Task: Conductor - User Manual Verification 'Phase 2: Core Services & Math Logic' (Protocol in workflow.md)