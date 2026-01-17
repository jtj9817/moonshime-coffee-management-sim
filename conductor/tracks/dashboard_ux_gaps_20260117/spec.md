# Track Specification: Dashboard UX & Test Validation Gaps

## Overview
This track focuses on closing critical gaps between the current UI and the `GameplayLoopVerificationTest` requirements. It implements essential logistics controls (Route Selection, Capacity Validation, Order Cancellation) and persistent game state visibility (Day Counter, Cash Display) to enable full manual and automated verification of the core gameplay loop.

## Functional Requirements

### 1. Critical Gaps (Priority 1: Blocking Test Validation)
- **Route Selection UI:**
    - Replace the "Dead-End" New Order button with a functional `RoutePicker` in `/game/ordering`.
    - Allow choosing between **Standard (Truck)** and **Premium (Air)** routes.
    - Display cost, transit time, and weather vulnerability per route.
    - Disable and flag blocked routes (e.g., during Blizzard).
- **Order Cancellation Workflow:**
    - Add "Cancel & Refund" action for **Shipped** orders.
    - **Block** cancellation for **Delivered** orders (hide button/disable API).
    - Provide immediate cash refund and status update upon cancellation.
- **Route Capacity Validation:**
    - Implement visual capacity meter (Green/Amber/Red) in ordering forms.
    - **Block** submission if order quantity exceeds route capacity.
    - Display error message with excess amount: "Order exceeds route capacity by X units".

### 2. Day Progression & Feedback (Priority 2)
- **Persistent Game State:**
    - Display "Day X of Y" counter in the global header/layout.
    - Display "Cash: $X,XXX,XXX" in the global header/layout.
    - Ensure these are visible on **all** game pages, not just Dashboard.
- **Advance Day Action:**
    - Add an explicit "Advance to Day X+1" button in the UI.

### 3. Day One Onboarding (Priority 3)
- **Welcome Experience:**
    - Display a "Welcome, Manager!" banner on Day 1.
    - Provide clear "Systems Normal" vs "Critical Stock" indicators.
    - Guide player to the first action: "Place First Order".

## Backend API Enhancements
- `GET /game/logistics/routes`: Return routes with optional `source_id`/`target_id` filtering (supports global view).
- `POST /game/orders/{id}/cancel`: Handle cancellation logic (validate status, refund cash).
- `GET /game/orders/{id}/capacity-check`: Server-side validation endpoint.

## Non-Functional Requirements
- **Real-time Feedback:** Inertia partial reloads for immediate UI updates.
- **Accessibility:** Ensure new controls are keyboard navigable and screen reader friendly (WCAG 2.1).
- **Responsiveness:** New UI elements must function on mobile devices.

## Acceptance Criteria
- [ ] **Route Selection:** Players can choose Standard vs. Premium routes; blocked routes are unselectable.
- [ ] **Capacity:** Orders > route capacity are blocked with specific error messages.
- [ ] **Cancellation:** Shipped orders can be cancelled for refund; Delivered orders cannot.
- [ ] **Visibility:** Day Counter and Cash Balance are persistent across the entire game session.
- [ ] **Day One:** User sees Welcome Banner and clear call-to-action to place the first order.
