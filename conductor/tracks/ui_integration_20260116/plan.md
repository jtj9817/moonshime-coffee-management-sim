# Implementation Plan - UI Integration ("The No-Map Dashboard")

## Phase 1: Backend Metrics & API Extensions
- [ ] Task: Update `DashboardController` to Expose Logistics Metrics
    - [ ] Write feature test verifying the Dashboard props include `logistics_health` and `active_spikes_count`.
    - [ ] Implement logic in `DashboardController` to calculate connectivity (active/total routes).
    - [ ] Implement logic to count active `SpikeEvents`.
- [ ] Task: Create Logistics Pathfinding API
    - [ ] Write integration test for `GET /api/logistics/path` endpoint.
    - [ ] Create `LogisticsController` and register the route.
    - [ ] Implement `getPath` method using `LogisticsService::findBestRoute`.
- [ ] Task: Conductor - User Manual Verification 'Backend Metrics & API Extensions' (Protocol in workflow.md)

## Phase 2: Dashboard Widget Implementation
- [ ] Task: Create `LogisticsStatusWidget` Component
    - [ ] Create the React component styled with Tailwind for the "High-Stakes Professionalism" look.
    - [ ] Integrate the widget into the main `Dashboard.tsx` page.
    - [ ] Verify the widget correctly displays props from Inertia.
- [ ] Task: Conductor - User Manual Verification 'Dashboard Widget Implementation' (Protocol in workflow.md)

## Phase 3: Restock Form Enhancement
- [ ] Task: Implement Route Awareness in Transfer Form
    - [ ] Update `RestockForm.tsx` to fetch route status when source/target locations are selected.
    - [ ] Implement UI state for "Blocked Route" (disable primary option, display warning message).
- [ ] Task: Implement "Switch to Alternative" Logic
    - [ ] Add the "Switch to Alternative" button to the form.
    - [ ] Implement the client-side logic to fetch the cheapest path via the new API.
    - [ ] Update cost calculations and form submission to reflect the selected alternative route.
- [ ] Task: Conductor - User Manual Verification 'Restock Form Enhancement' (Protocol in workflow.md)

## Phase 4: Full System Integration & Checkpointing
- [ ] Task: Integration Test - Full Disruption Lifecycle
    - [ ] Write a comprehensive test: Trigger Blizzard -> Verify Dashboard Metric Update -> Verify Restock Form Blockage -> Verify Alternative Suggestion.
- [ ] Task: Conductor - User Manual Verification 'Full System Integration & Checkpointing' (Protocol in workflow.md)
