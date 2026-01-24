# Track Specification: Analytics Data Integration & Enhancement

## Overview
Refactor the Analytics dashboard to replace placeholder data with real game metrics and expand the dashboard with new logistics and financial insights. This includes implementing a historical snapshot system for inventory and transitioning the UI to a tabbed interface.

## Functional Requirements
- **Real-Time Data Integration**: 
    - Replace hard-coded `inventoryTrends` with real historical data from a new `inventory_history` table.
    - Replace random `spendingByCategory` values with actual sums from `orders` and `order_items`.
    - Enhance `locationComparison` with utilization percentages and item counts.
- **New Analytics Metrics**:
    - **Storage Utilization**: Per-location capacity vs. current usage.
    - **Order Fulfillment**: Success rate of delivered vs. pending/cancelled orders and average delivery time.
    - **Spike Impact**: Historical view of demand spikes and their effect on inventory.
- **Backend Infrastructure**:
    - Create `inventory_history` table.
    - Implement a daily snapshot listener to record inventory levels at the end of each game day.
    - Add `unit_price` to `products` table for more accurate value calculations.
- **UI Refactoring**:
    - Transition the Analytics page to a **Tabbed View** with three sections:
        - **Overview**: High-level summary cards and inventory trends.
        - **Logistics**: Storage utilization and spike impact analysis.
        - **Financials**: Spending by category and order fulfillment metrics.

## Technical Requirements
- **Database**:
    - Migration for `inventory_history` table (user_id, location_id, product_id, day, quantity).
    - Migration to add `unit_price` to `products`.
    - Performance indices on `products.category` and `daily_reports(user_id, day)`.
- **Backend**:
    - Update `GameController::analytics()` to provide enhanced props.
    - Optimize queries to avoid N+1 issues (use eager loading/aggregates).
- **Frontend**:
    - Update `AnalyticsProps` interface in `analytics.tsx`.
    - Implement tab logic and new chart components for expanded metrics.

## Acceptance Criteria
- [ ] `inventoryTrends` displays real data from `inventory_history`.
- [ ] `spendingByCategory` reflects actual order history totals.
- [ ] Analytics page successfully uses a tabbed navigation system.
- [ ] Automated tests verify that data calculations are accurate and scoped to the authenticated user.
- [ ] Page load performance remains acceptable (no excessive query counts).

## Out of Scope
- Date range filtering (planned for a future track).
- Exporting data to CSV/PDF.
- Real-time updates via WebSockets.
