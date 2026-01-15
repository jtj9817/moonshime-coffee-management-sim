# Technology Stack: Moonshine Coffee Management Sim

## Core Frameworks
- **Backend:** PHP 8.2+ with Laravel 12
- **Frontend:** React 19 with TypeScript
- **Bridge:** Inertia.js 2.0

## Architecture & State Management
- **Inertia.js Protocol:** The server acts as the single source of truth. Data is passed as props from Laravel controllers directly to React components.
- **Backend Model State:** `spatie/laravel-model-states` is used to manage finite state machines for complex domain models (e.g., Orders, Transfers).
- **Authentication:** Laravel Fortify

## Database & Storage
- **Primary Database:** PostgreSQL
- **Testing/Dev Database:** SQLite
- **ORM:** Eloquent

## Styling & UI
- **CSS Framework:** Tailwind CSS 4.0
- **Build Tool:** Vite

## Additional Libraries (Inferred)
- **Real-time:** Laravel Reverb (planned)