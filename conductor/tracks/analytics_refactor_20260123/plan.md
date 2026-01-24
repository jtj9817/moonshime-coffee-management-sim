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

## Phase 3: Extended Analytics Logic [checkpoint: 00adb7b]
- [x] Task: Implement `getStorageUtilization`
    - [x] Write test for storage utilization logic
    - [x] Implement backend method in `GameController`
- [x] Task: Implement `getOrderFulfillmentMetrics`
    - [x] Write test for fulfillment rate and avg delivery time calculations
    - [x] Implement backend method in `GameController`
- [x] Task: Implement `getSpikeImpactAnalysis`
    - [x] Write test for spike correlation logic
    - [x] Implement backend method in `GameController`
- [x] Task: Conductor - User Manual Verification 'Phase 3: Extended Analytics Logic' (Protocol in workflow.md)

## Phase 4: Frontend UI Refactoring & Fixes [checkpoint: pending]
- [x] Task: Update TypeScript interfaces
    - [x] Define `EnhancedAnalyticsProps` in `analytics.tsx`
- [x] Task: Create Tabs Component
    - [x] Create basic `Tabs` and `TabsContent` wrapper component (or scaffold standard UI pattern)
    - [x] Verify tab switching state works independently
- [x] Task: Implement 'Overview' Tab Component
    - [x] Move existing Summary Cards and Inventory Trends Chart into an `OverviewTab` component
    - [x] Wire up real data props to `OverviewTab`
- [x] Task: Implement 'Logistics' Tab Component
    - [x] Create `LogisticsTab` component
    - [x] Implement Storage Utilization Chart
    - [x] Implement Spike Impact List view
    - [x] Wire up props
- [x] Task: Implement 'Financials' Tab Component
    - [x] Create `FinancialsTab` component
    - [x] Implement Spending by Category Chart (using real data)
    - [x] Implement Order Fulfillment Metrics display
    - [x] Wire up props
- [x] Task: Integrate Tabs into Main Analytics Page
    - [x] Replace single-page layout in `analytics.tsx` with the new Tabbed components
    - [x] Pass correct props to each tab component
- [~] Task: Fix Overview Summary Cards Visibility
    - [ ] Investigate and fix rendering of "Cash on Hand", "Net Worth", and "7-Day Revenue" cards in `OverviewTab`
    - [ ] Verify prop passing from parent component
- [ ] Task: Debug & Fix Inventory/Location Data
    - [ ] Debug backend data retrieval for `getInventoryTrends` (check for empty history)
    - [ ] Debug backend data retrieval for `getLocationComparison`
    - [ ] Verify frontend chart mapping in `OverviewTab`
- [ ] Task: Implement Collapsible UI Sections
    - [ ] Create `CollapsibleSection` component (using Radix UI or similar)
    - [ ] Wrap "Inventory Trends" and "Location Comparison" in `OverviewTab`
    - [ ] Wrap "Storage Utilization" and "Spike Impact Analysis" in `LogisticsTab`
- [ ] Task: Conductor - User Manual Verification 'Phase 4: Frontend UI Refactoring & Fixes' (Protocol in workflow.md)
