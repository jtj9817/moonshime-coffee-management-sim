# Implementation Plan - UI Integration ("The No-Map Dashboard")

## Phase 1: Backend Metrics & API Extensions
- [x] Task: Update `DashboardController` to Expose Logistics Metrics
    - [x] Write feature test verifying the Dashboard props include `logistics_health` and `active_spikes_count`.
    - [x] Implement logic in `DashboardController` to calculate connectivity (active/total routes).
    - [x] Implement logic to count active `SpikeEvents`.
- [x] Task: Create Logistics Pathfinding API
    - [x] Write integration test for `GET /api/logistics/path` endpoint.
    - [x] Create `LogisticsController` and register the route.
    - [x] Implement `getPath` method using `LogisticsService::findBestRoute`.
- [x] Task: Conductor - User Manual Verification 'Backend Metrics & API Extensions' (Protocol in workflow.md)

## Phase 2: Dashboard Widget Implementation
- [x] Task: Create `LogisticsStatusWidget` Component
    - [x] Create the React component styled with Tailwind for the "High-Stakes Professionalism" look.
    - [x] Integrate the widget into the main `Dashboard.tsx` page.
    - [x] Verify the widget correctly displays props from Inertia.
- [x] Task: Conductor - User Manual Verification 'Dashboard Widget Implementation' (Protocol in workflow.md)

## Phase 3: Restock Form Enhancement
- [x] Task: Implement Route Awareness in Transfer Form
    - [x] Update `RestockForm.tsx` (implemented in `transfers.tsx`) to fetch route status when source/target locations are selected.
    - [x] Implement UI state for "Blocked Route" (disable primary option, display warning message).
- [x] Task: Implement "Switch to Alternative" Logic
    - [x] Add the "Switch to Alternative" button to the form.
    - [x] Implement the client-side logic to fetch the cheapest path via the new API.
    - [x] Update cost calculations and form submission to reflect the selected alternative route.

## Phase 4: Full System Integration & Checkpointing [checkpoint: 6df6b60]
- [x] Task: Integration Test - Full Disruption Lifecycle
    - [x] Write a comprehensive test: Trigger Blizzard -> Verify Dashboard Metric Update -> Verify Restock Form Blockage -> Verify Alternative Suggestion.
- [x] Task: Conductor - User Manual Verification 'Full System Integration & Checkpointing' (Protocol in workflow.md)
