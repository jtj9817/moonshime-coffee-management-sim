# Moonshine Coffee Management Sim

A Laravel 12 + React 19 simulation game for managing coffee shop inventory and supply chain operations across multiple locations. Built with Inertia.js for a seamless SPA-like experience with server-side rendering.

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.2+) with PostgreSQL
- **Frontend**: React 19 + TypeScript via Inertia.js 2.0
- **Styling**: TailwindCSS 4.0 with Radix UI components
- **Real-time**: Laravel Reverb for dashboard updates (planned)
- **State Management**: Laravel state machines via `spatie/laravel-model-states`
- **Routing**: Laravel Wayfinder for type-safe routing
- **AI Integration**: Echolabs Prism for AI-powered inventory advisories
- **Package Management**: pnpm for frontend, Composer for backend

## Project Overview

Moonshine Coffee Management Sim is a supply chain management simulation game. Players manage three coffee shop locations:

- **Moonshine HQ (Roastery)** - Central roastery and warehouse (5,000 unit capacity)
- **Uptown Kiosk** - Small downtown kiosk (500 unit capacity)
- **Lakeside Cafe** - Larger cafe location (1,200 unit capacity)

Players must balance inventory across locations, place orders from multiple suppliers, manage transfers between locations, and respond to random demand spikes while optimizing costs and avoiding stockouts and waste.

## Core Features

### Inventory Management
- Track inventory levels across all three locations
- Monitor perishable items with expiration dates (Oat Milk, Pumpkin Spice, Bacon Gouda sandwiches)
- Calculate reorder points and safety stock levels
- Handle waste from expired or spoiled inventory

### Supplier Management
- Multiple suppliers with different trade-offs (reliability vs price vs speed)
  - BeanCo Global - High reliability, standard delivery, premium pricing
  - RapidSupplies - Fast delivery, moderate pricing, lower reliability
  - Dairy Direct - Excellent reliability for dairy products
  - ValueBulk - Best prices, slow delivery, lowest reliability
- Tiered pricing with volume discounts
- Supplier performance metrics tracking (late rate, fill rate, complaint rate)

### Demand Spikes & Events
- Random demand spikes (Chaos Monkey events)
- Heatwaves, local festivals, viral social media trends
- Supplier strikes and delivery delays
- Spike resolution and recovery mechanics

### Transfer Logistics
- Transfer inventory between locations
- Handle in-transit transfers with state management
- Balance stock across locations based on demand patterns

### AI-Powered Advisories
- AI-generated inventory recommendations
- Contextual advice based on current stock levels
- Supplier selection assistance

### Analytics & Reporting
- Dashboard with KPIs and key metrics
- Inventory position reports
- Vendor performance analytics
- Waste tracking and cost analysis
- Historical spike event logs

## Architecture

### Backend Design Patterns

The application follows strict architectural patterns for maintainability and scalability:

- **Dependency Injection**: Services injected via constructor, no direct instantiation in controllers
- **Service Providers**: `GameServiceProvider` binds interfaces to implementations
- **Observer Pattern**: Events decouple actions from side effects (e.g., `OrderPlaced` event triggers cash deduction, notifications, metrics updates)
- **State Pattern**: Finite state machines for Order and Transfer lifecycles (`Draft` -> `Pending` -> `Shipped` -> `Delivered`)
- **Strategy Pattern**: Restock strategies (`JustInTimeStrategy`, `SafetyStockStrategy`) based on user policy settings
- **Factory Pattern**: `SpikeEventFactory` generates random simulation events
- **DTOs**: Strictly typed data transfer objects (`InventoryAdvisoryData`, `InventoryContextDTO`)

### Frontend Architecture

- **Inertia.js Pages**: React pages at `resources/js/Pages/game/` corresponding to Laravel routes
- **Persistent Layouts**: `GameLayout.tsx` preserves navigation and HUD during navigation
- **Global State**: Shared via Inertia middleware (`HandleInertiaRequests`) instead of client-side store
- **Partial Reloads**: Optimized updates for polling components (`router.reload({ only: ['spikes'] })`)
- **Form Handling**: Inertia's `useForm` hook with automatic validation error mapping
- **Type Safety**: TypeScript interfaces mirror backend DTOs and API Resources

## Directory Structure

```
moonshine-coffee-management-sim/
├── app/
│   ├── Models/           # Eloquent models (Location, Product, Vendor, Inventory, Order, etc.)
│   ├── Services/         # Business logic services (InventoryManagement, Simulation, AI, etc.)
│   └── Http/Controllers/ # Request handlers with Inertia responses
├── resources/js/
│   ├── components/       # Reusable React UI components
│   ├── pages/            # Inertia pages (Dashboard, Inventory, Orders, etc.)
│   ├── layouts/          # Persistent layouts (GameLayout)
│   ├── services/         # Frontend services (cockpit, inventory, spike services)
│   ├── constants.ts      # Game constants (LOCATIONS, ITEMS, SUPPLIERS)
│   └── types.ts          # TypeScript type definitions
├── routes/               # Laravel routes
├── database/             # Migrations, factories, seeders
└── tests/                # PHPUnit feature and unit tests
```

## Game Entities

### Locations
- Moonshine HQ (Roastery) - 5,000 capacity
- Uptown Kiosk - 500 capacity
- Lakeside Cafe - 1,200 capacity

### Product Categories
- Beans (Espresso Blend)
- Milk (Oat Milk, Almond Milk)
- Cups (12oz Paper Cups)
- Syrups (Vanilla, Dark Mocha)
- Sauces (Pumpkin Spice)
- Tea (Earl Grey)
- Sugar (Raw Sugar)
- Food (Bacon Gouda Sandwiches)
- Cleaning (Sanitizer Spray)

### Suppliers
- BeanCo Global - 95% reliability, 3-day delivery, premium pricing
- RapidSupplies - 85% reliability, 1-day delivery, moderate pricing
- Dairy Direct - 98% reliability, 1-day delivery for dairy
- ValueBulk - 70% reliability, 7-day delivery, discount pricing

## Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- pnpm
- PostgreSQL (or use Sail for Docker)

### Quick Start with Docker (Recommended)

```bash
# Clone the repository
git clone <repository-url>
cd moonshine-coffee-management-sim

# Copy environment file
cp .env.example .env

# Start containers and install dependencies
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# Generate application key and run migrations
./vendor/bin/sail php artisan key:generate
./vendor/bin/sail php artisan migrate

# Build assets
./vendor/bin/sail npm run dev

# Access the application at http://localhost:8082
```

### Local Development without Docker

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
pnpm install

# Copy environment and configure database
cp .env.example .env
# Edit .env to set your database credentials

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
composer dev

# Or start services separately:
php artisan serve
php artisan queue:listen
php artisan pail
pnpm dev
```

### Database Setup

The project uses PostgreSQL by default. Configure your database in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moonshine_coffee
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Or use SQLite for local development:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

## Development Commands

### Backend

```bash
# Run full development stack (Laravel + queue + logs + Vite)
composer dev

# Run with SSR support
composer dev:ssr

# PHP code formatting (Pint)
composer lint

# Run tests with lint check
composer test

# Laravel Artisan commands
php artisan migrate
php artisan migrate:fresh --seed
php artisan tinker
```

### Frontend

```bash
# Vite dev server
pnpm dev

# Production builds
pnpm build
pnpm build:ssr

# ESLint with auto-fix
pnpm lint

# Prettier formatting
pnpm format
pnpm format:check

# TypeScript type checking
pnpm types
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=InventoryTest

# Run Pest tests
vendor/bin/pest
```

## Game Mechanics

### Inventory Calculations

The simulation uses sophisticated inventory math including:

- **Reorder Point**: `Average Daily Demand × Lead Time + Safety Stock`
- **Economic Order Quantity (EOQ)**: Optimizes ordering costs vs holding costs
- **Safety Stock**: Buffer stock to prevent stockouts during demand spikes
- **Service Levels**: Target fill rates (95%, 98%, 99%)

### State Machine Transitions

Orders and transfers follow strict state transitions:

```
Order: Draft -> Pending -> Shipped -> Delivered -> Cancelled
Transfer: Requested -> Approved -> InTransit -> Completed -> Cancelled
```

### Event System

The simulation fires events that trigger listeners:

- `TimeAdvanced`: Triggers perishable decay, delivery processing, spike generation
- `OrderPlaced`: Deducts cash, notifies warehouse, updates vendor metrics
- `LowStockDetected`: Creates alerts, suggests restock actions
- `SpikeOccurred`: Records spike event, generates resolution strategies

## Code Quality

### PHP Standards
- PSR-12 coding standard
- Laravel Pint for automatic code formatting
- Type hints on all methods
- PHPDoc for complex logic

### TypeScript Standards
- Strict mode enabled
- ESLint with React plugin
- Prettier for formatting
- Proper interface definitions

### Testing
- Feature tests for controller actions
- Unit tests for services and models
- Factory classes for test data generation
- RefreshDatabase trait for test isolation

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME=MoonshineCoffeeSim
APP_URL=http://localhost:8082
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moonshine_coffee

# Vite ports
VITE_PORT=5174
```

### AI Configuration

The application uses Echolabs Prism for AI integrations. Configure API keys in your environment or service provider.

## License

This project is open-source and available under the MIT License.
