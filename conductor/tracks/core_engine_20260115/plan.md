# Implementation Plan - Core Inventory & Supply Chain Engine

## Phase 1: Database Schema & Core Models
- [ ] Task: Create Migrations for Core Entities
    - [ ] Create migration for `locations` table
    - [ ] Create migration for `vendors` table
    - [ ] Create migration for `products` table
    - [ ] Create migration for `inventories` table (with foreign keys)
    - [ ] Run migrations to verify schema
- [ ] Task: Implement Eloquent Models
    - [ ] Create `Location` model with relationships
    - [ ] Create `Vendor` model with relationships
    - [ ] Create `Product` model with relationships
    - [ ] Create `Inventory` model with relationships
- [ ] Task: Create Model Factories and Seeders
    - [ ] Create Factories for all 4 models
    - [ ] Create Seeder to populate initial game state
- [ ] Task: Implement InventoryObserver
    - [ ] Create `LowStockDetected` event
    - [ ] Create `InventoryObserver` to watch `updated` event
    - [ ] Register observer in `AppServiceProvider`
- [ ] Task: Conductor - User Manual Verification 'Phase 1: Database Schema & Core Models' (Protocol in workflow.md)

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