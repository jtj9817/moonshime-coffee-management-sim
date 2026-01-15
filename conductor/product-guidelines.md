# Product Guidelines: Moonshine Coffee Management Sim

## Communication & Prose Style
- **Professional & Precise:** In-game text and documentation must use technical, logistics-oriented language (e.g., "Inventory Reconciliation," "Vendor Reliability Index"). Avoid fluff; prioritize clarity and professional tone to reinforce the high-stakes corporate environment.

## UI/UX Principles
- **Visual Feedback:** Use distinct animations, color cues, and haptic-style transitions to signal critical simulation events (e.g., urgent stockouts, budget alerts).
- **Hierarchy & Clarity:** Layouts must maintain a clear visual hierarchy, ensuring the most vital information (e.g., Cash Balance, Game Time) is immediately accessible and prominent.

## Engineering Standards
- **Strict Architectural Adherence:** Maintain high maintainability through rigid design patterns. Every business logic operation must reside in a Service; state transitions must use the State Pattern; data transfer across boundaries must use DTOs.
- **Full Type Safety:** Utilize strictly typed PHP and TypeScript to ensure code reliability and developer productivity.

## Simulation & Balance Principles
- **Logistics Realism:** Prioritize realistic logistics formulas and "hard" constraints. The simulation should feel grounded in actual supply chain principles like Economic Order Quantity (EOQ) and Safety Stock calculations.

## Visual Identity & Theme
- **Adaptive Professionalism:** The visual theme must support both **Dark Mode** and **Light Mode**. 
    - **Dark Mode:** Analytical, high-contrast aesthetic using slates and indigos.
    - **Light Mode:** A clean, professional "paper-like" interface that maintains high legibility and data density.
- **Minimalist Iconography:** Use consistent, professional icons to create a focused, high-performance atmosphere across both modes.