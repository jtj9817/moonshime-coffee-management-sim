# Implementation Plan: Dashboard UX & Test Validation Gaps

## Phase 1: Global State & Layout Foundations [checkpoint: 147a777]
- [x] Task: Add Day Counter and Cash Display to GameLayout
    - [x] Create `DayCounter` component
    - [x] Create `CashDisplay` component with animation support
    - [x] Integrate components into `resources/js/layouts/game-layout.tsx`
- [x] Task: Implement Advance Day UI trigger
    - [x] Add `AdvanceDayButton` to header or dashboard
    - [x] Connect button to `advanceDay()` in `GameContext`
- [ ] Task: Conductor - User Manual Verification 'Global State & Layout Foundations' (Protocol in workflow.md)

## Phase 2: Backend API Enhancements [checkpoint: 672bd7c]
- [x] Task: Implement Route Retrieval API
    - [x] Write tests for `GET /game/logistics/routes`
    - [x] Implement endpoint in `app/Http/Controllers/GameController.php` (or dedicated Logistics controller)
    - [x] Ensure support for `source_id`/`target_id` filtering
- [x] Task: Implement Order Cancellation API
    - [x] Write tests for `POST /game/orders/{id}/cancel`
    - [x] Implement cancellation logic in `OrderController`
    - [x] Add state machine validation (Shipped only, not Delivered)
    - [x] Add atomic cash refund logic
- [x] Task: Conductor - User Manual Verification 'Backend API Enhancements' (Protocol in workflow.md)

## Phase 3: Route Selection & Capacity UI
- [x] Task: Create RoutePicker Component
    - [x] Design `RoutePicker` with transport mode, cost, and transit info
    - [x] Implement visual indicators for Premium and Blocked routes
    - [x] Integrate into `resources/js/pages/game/ordering.tsx`
- [x] Task: Implement Real-time Capacity Validation
    - [x] Create `RouteCapacityMeter` component (Green/Amber/Red)
    - [x] Add real-time calculation in `ordering.tsx`
    - [x] Disable submit button and show specific error on excess
- [ ] Task: Conductor - User Manual Verification 'Route Selection & Capacity UI' (Protocol in workflow.md)

## Phase 4: Order Cancellation UI
- [x] Task: Add Cancellation controls to Ordering Page
    - [x] Add "Cancel & Refund" button to orders table
    - [x] Implement state check (visible only for Shipped orders)
- [x] Task: Implement Cancellation Confirmation Dialog
    - [x] Create dialog showing refund amount and warnings
    - [x] Hook up to `cancelOrder` API call
- [ ] Task: Conductor - User Manual Verification 'Order Cancellation UI' (Protocol in workflow.md)

## Phase 5: Day One Onboarding & Polish
- [x] Task: Implement Day 1 Welcome Banner
    - [x] Create `WelcomeBanner` component
    - [x] Add conditional rendering in `dashboard.tsx` for `day === 1`
- [x] Task: Improve Empty State Messaging
    - [x] Update `LocationCard` to show critical warnings when inventory is 0
    - [x] Add "Place First Order" call-to-action
- [x] Task: Conductor - User Manual Verification 'Day One Onboarding & Polish' (Protocol in workflow.md)
