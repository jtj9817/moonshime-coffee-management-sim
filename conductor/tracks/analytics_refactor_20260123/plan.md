# Implementation Plan: Analytics Data Integration & Enhancement

## Phase 1: Database Migrations & Historical Tracking [checkpoint: fa8fe14]
- [x] Task: Create migration for `unit_price` and indices
    - [x] Create migration adding `unit_price` to `products`
    - [x] Add indices for `products.category` and `daily_reports(user_id, day)`
    - [x] Run migration
- [x] Task: Create `inventory_history` migration
    - [x] Create migration for `inventory_history` (user_id, location_id, product_id, day, quantity)
    - [x] Add composite unique constraint on (user_id, location_id, product_id, day)
    - [x] Run migration
- [x] Task: Implement Inventory Snapshot Listener
    - [x] Write test for `SnapshotInventoryLevels` listener triggered by `TimeAdvanced`
    - [x] Implement listener logic to copy current `inventories` to `inventory_history` for the current day
    - [x] Verify listener handles duplicate runs gracefully
- [x] Task: Conductor - User Manual Verification 'Phase 1: Database Migrations & Historical Tracking' (Protocol in workflow.md)

## Phase 2: Data Provider Refactoring (Existing Metrics) [checkpoint: 64a7b3e]
- [x] Task: Refactor `getInventoryTrends`
    - [x] Write test for `getInventoryTrends` querying `inventory_history`
    - [x] Implement query logic in `GameController`
- [x] Task: Refactor `getSpendingByCategory`
    - [x] Write test for `getSpendingByCategory` joining `orders` and `order_items`
    - [x] Implement query logic in `GameController` calculating real sums
- [x] Task: Enhance `getLocationComparison`
    - [x] Write test for enhanced location comparison (utilization %, item counts)
    - [x] Refactor logic in `GameController` with eager loading to avoid N+1
- [x] Task: Conductor - User Manual Verification 'Phase 2: Data Provider Refactoring (Existing Metrics)' (Protocol in workflow.md)

## Phase 3: Extended Analytics Logic
- [ ] Task: Implement `getStorageUtilization`
    - [ ] Write test for storage utilization logic
    - [ ] Implement backend method in `GameController`
- [ ] Task: Implement `getOrderFulfillmentMetrics`
    - [ ] Write test for fulfillment rate and avg delivery time calculations
    - [ ] Implement backend method in `GameController`
- [ ] Task: Implement `getSpikeImpactAnalysis`
    - [ ] Write test for spike correlation logic
    - [ ] Implement backend method in `GameController`
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Extended Analytics Logic' (Protocol in workflow.md)

## Phase 4: Frontend UI Refactoring
- [ ] Task: Update TypeScript interfaces
    - [ ] Define `EnhancedAnalyticsProps` in `analytics.tsx`
- [ ] Task: Create Tabs Component
    - [ ] Create basic `Tabs` and `TabsContent` wrapper component (or scaffold standard UI pattern)
    - [ ] Verify tab switching state works independently
- [ ] Task: Implement 'Overview' Tab Component
    - [ ] Move existing Summary Cards and Inventory Trends Chart into an `OverviewTab` component
    - [ ] Wire up real data props to `OverviewTab`
- [ ] Task: Implement 'Logistics' Tab Component
    - [ ] Create `LogisticsTab` component
    - [ ] Implement Storage Utilization Chart
    - [ ] Implement Spike Impact List view
    - [ ] Wire up props
- [ ] Task: Implement 'Financials' Tab Component
    - [ ] Create `FinancialsTab` component
    - [ ] Implement Spending by Category Chart (using real data)
    - [ ] Implement Order Fulfillment Metrics display
    - [ ] Wire up props
- [ ] Task: Integrate Tabs into Main Analytics Page
    - [ ] Replace single-page layout in `analytics.tsx` with the new Tabbed components
    - [ ] Pass correct props to each tab component
- [ ] Task: Conductor - User Manual Verification 'Phase 4: Frontend UI Refactoring' (Protocol in workflow.md)
