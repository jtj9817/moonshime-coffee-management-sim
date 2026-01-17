# Specification: UI Integration ("The No-Map Dashboard")

## 1. Overview
This track implements the frontend integration for the Hybrid Event-Topology system. It connects the backend logistics graph and simulation ticks to the user interface. The goal is to provide players with clear visibility into supply chain health and actionable alternatives when disruptions (Spikes) occur, all while maintaining the "No-Map" analytical aesthetic.

## 2. Functional Requirements

### 2.1 Logistics Health Dashboard Widget
- **Location:** Main Statistics Row (alongside Cash/Reputation).
- **Metrics to Display:**
    - **Connectivity %:** The ratio of active routes to total routes (e.g., "Logistics Health: 85%").
    - **Disruption Count:** Number of currently active Spike Events affecting the logistics network.
- **Inertia Integration:** The `DashboardController` must pass these calculated values as props to the React dashboard.

### 2.2 Intelligent Restock/Transfer Form
- **Route Validation:** When a source and target are selected, the form must check the status of the primary route.
- **Blocked State UX:** If the primary route is inactive (e.g., due to a Blizzard):
    - Display the primary route as **Disabled**.
    - Show a "Route Blocked" warning message.
    - Provide a "Switch to Alternative" button.
- **Alternative Suggestion:** 
    - The system will query a new API endpoint (`/api/logistics/path`) to find the **Cheapest Valid Path** using the existing Dijkstra implementation in `LogisticsService`.
    - The alternative route (e.g., "Emergency Air Drop") must be displayed with its cost clearly highlighted to indicate the premium price.

### 2.3 Backend API Extensions
- **Logistics Endpoint:** Create an endpoint to return the optimal path between two locations based on current graph weights.
- **Metric Logic:** Implement logic to aggregate total vs. active routes for the connectivity percentage.

## 3. Non-Functional Requirements
- **Feedback Loop:** Form validation and alternative route calculation should feel instantaneous (<200ms latency).
- **Visual Style:** Adhere to the "High-Stakes Professionalism" aesthetic, using clear warnings and analytical data displays instead of a visual map.

## 4. Acceptance Criteria
- Dashboard displays the "Logistics Health" percentage and active spike count.
- The Restock form correctly identifies and disables blocked primary routes.
- The system suggests the cheapest alternative route when a primary route is unavailable.
- Users can successfully execute a transfer using an alternative route at the calculated cost.

## 5. Out of Scope
- A visual map or spatial representation of the locations.
- Real-time "Push" notifications for route blockages (handled via dashboard refresh for now).
