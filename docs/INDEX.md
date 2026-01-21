# Moonshine Coffee Management Sim - Documentation Index

Welcome to the comprehensive documentation for the Moonshine Coffee Management Sim project.

## Project Overview

Moonshine Coffee Management Sim is a **turn-based supply chain management simulation game** where players manage inventory, orders, and logistics across three coffee shop locations (Roastery HQ, Uptown Kiosk, and Lakeside Cafe). Built with **Laravel 12**, **React 19**, and **Inertia.js 2.0**.

## Quick Links

### Getting Started
- [README.md](../README.md) - Project setup and installation
- [CLAUDE.md](../CLAUDE.md) - AI agent development guidelines
- [Technical Design Document](./technical-design-document.md) - High-level architecture

### ⚠️ Active Issues
- **[Data Seeding & Synchronization Analysis](./data-seeding-synchronization-analysis.md)** - Critical frontend-backend data mismatches (2026-01-20)

### Core Documentation

#### 📦 Backend (Laravel)
Comprehensive documentation for the Laravel backend, including models, controllers, services, and events.

- **[Backend README](./backend/README.md)** - Overview and navigation
- [Architecture](./backend/01-architecture.md) - Design patterns and layers
- [Models & Database](./backend/02-models-database.md) - Database schema and Eloquent models
- [Controllers & Routes](./backend/03-controllers-routes.md) - HTTP layer and routing

#### 🎮 Domain (Game Logic)
Business rules, game mechanics, and simulation logic that drive the gameplay experience.

- **[Domain README](./domain/README.md)** - Overview and navigation
- [Game Mechanics](./domain/01-game-mechanics.md) - Core gameplay loop and rules

#### ⚛️ Frontend (React)
Modern React application with TypeScript, Inertia.js, and TailwindCSS.

- **[Frontend README](./frontend/README.md)** - Overview and navigation

## Documentation Structure

```
docs/
├── INDEX.md                          # This file - main documentation index
├── architecture/                     # Architecture diagrams and notes
├── archived-plans/                   # Historical planning documents
│
├── backend/                          # Backend (Laravel) documentation
│   ├── README.md                     # Backend overview
│   ├── 01-architecture.md            # Architecture and design patterns
│   ├── 02-models-database.md         # Models, schema, relationships
│   └── 03-controllers-routes.md      # Controllers, routes, middleware
│
├── domain/                           # Domain logic documentation
│   ├── README.md                     # Domain overview
│   └── 01-game-mechanics.md          # Game rules and mechanics
│
├── frontend/                         # Frontend (React) documentation
│   └── README.md                     # Frontend overview
│
├── daily-reporting-infrastructure-analysis.md
├── daily-simulation-logic-plan.md
├── dashboard-ux-test-gap-analysis.md
├── data-seeding-synchronization-analysis.md  # ⚠️ Active issue - Frontend/Backend data mismatch
├── gameplay-loop-mechanics-analysis.md
├── game-state-persistence-brainstorm.md
├── logistics-integration-post-completion-cleanup.md
├── moonshine-laravel-integration-plan.md
├── multi-hop-implementation-summary.md
├── multi_hop_proposal.md
├── ordering-dialog-fix-session-20260119.md
├── route-routing-review.md
├── technical-design-document.md
└── test-execution-report-20260117.md
```

## Tech Stack Summary

### Backend
- **Framework**: Laravel 12
- **Language**: PHP 8.2+
- **Database**: PostgreSQL
- **Authentication**: Laravel Fortify
- **API Bridge**: Inertia.js 2.0
- **Routing**: Laravel Wayfinder

### Frontend
- **Framework**: React 19
- **Language**: TypeScript 5.x (strict mode)
- **Styling**: TailwindCSS 4.0
- **UI Components**: Radix UI
- **Icons**: Lucide React
- **Build Tool**: Vite 5.x
- **SSR**: Inertia SSR

### Development Tools
- **Linting**: ESLint (frontend), Pint (backend)
- **Formatting**: Prettier
- **Testing**: PHPUnit/Pest (backend), Vitest (frontend)
- **Git Hooks**: Pre-commit linting

## Key Features

### ✅ Implemented
- [x] Multi-location inventory management
- [x] Vendor ordering with pricing tiers
- [x] Internal transfer system
- [x] Multi-hop routing and logistics
- [x] Dynamic spike events (demand, delay, price, breakdown, blizzard)
- [x] Alert system with severity levels
- [x] Turn-based time advancement
- [x] Storage capacity management
- [x] Perishable item tracking
- [x] Cash and reputation management
- [x] AI-powered inventory recommendations (Prism AI)
- [x] Comprehensive dashboard with KPIs
- [x] Responsive UI with dark mode

### 🚧 Planned Enhancements
- [ ] Daily demand simulation (actual consumption)
- [ ] Revenue generation system
- [ ] Staff management
- [ ] Location expansion
- [ ] Advanced vendor negotiations
- [ ] Tutorial/onboarding flow
- [ ] Leaderboards and scoring
- [ ] Save/load game states
- [ ] Multiple difficulty levels

## Development Workflow

### Installation
```bash
# Clone repository
git clone <repository-url>
cd moonshime-coffee-management-sim

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set up database
php artisan migrate
php artisan db:seed

# Start development
composer dev         # Laravel + Vite
# OR
composer dev:ssr     # With SSR support
```

### Common Commands
```bash
# Development
composer dev            # Start dev server (Laravel + queue + Vite)
composer dev:ssr        # Dev server with SSR
npm run dev            # Frontend-only dev server

# Building
npm run build          # Build frontend for production
npm run build:ssr      # Build SSR bundle

# Testing
composer test          # Run backend tests
npm run test          # Run frontend tests

# Linting & Formatting
composer lint         # PHP lint with Pint
npm run lint         # ESLint with auto-fix
npm run format       # Prettier formatting
npm run types        # TypeScript type checking
```

## Architecture Overview

### High-Level Architecture

```
┌───────────────────────────────────────────────────────────┐
│                    User Browser                           │
│  ┌─────────────────────────────────────────────────────┐  │
│  │          React Application (TypeScript)             │  │
│  │  - Pages (Inertia)                                  │  │
│  │  - Components (UI + Game)                           │  │
│  │  - Services (Business Logic)                        │  │
│  │  - Contexts (State Management)                      │  │
│  └─────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
                            │
                   ┌────────┴────────┐
                   │   Inertia.js    │
                   │   (Bridge)      │
                   └────────┬────────┘
                            │
┌───────────────────────────────────────────────────────────┐
│                   Laravel Application                     │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Controllers (HTTP Layer)                           │  │
│  │  ├─ GameController                                  │  │
│  │  ├─ LogisticsController                             │  │
│  │  └─ Settings Controllers                            │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Services (Business Logic)                          │  │
│  │  ├─ SimulationService                               │  │
│  │  ├─ OrderService                                    │  │
│  │  ├─ LogisticsService                                │  │
│  │  ├─ InventoryManagementService                      │  │
│  │  └─ PrismAiService                                  │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Domain Layer (Models, Events, States)             │  │
│  │  ├─ Models (Eloquent ORM)                           │  │
│  │  ├─ Events (Domain Events)                          │  │
│  │  ├─ Listeners (Event Handlers)                      │  │
│  │  └─ State Machines (Order/Transfer)                 │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Data Layer (Database)                              │  │
│  │  - PostgreSQL                                       │  │
│  │  - Migrations                                       │  │
│  │  - Factories & Seeders                              │  │
│  └─────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
```

### Data Flow Example: Placing an Order

```
1. User clicks "Place Order" button in React
   └─> React component calls Inertia router.post()

2. Inertia sends POST request to Laravel
   └─> POST /game/orders

3. Laravel Route dispatches to GameController@storeOrder
   └─> Validates request via StoreOrderRequest

4. Controller delegates to OrderService@createOrder()
   └─> Business logic:
       - Validate inventory capacity
       - Calculate costs
       - Determine routing (via LogisticsService)
       - Create shipments

5. Order model created in database
   └─> OrderPlaced event dispatched

6. Event listeners execute:
   └─> DeductCash, UpdateMetrics, GenerateAlert

7. Controller returns Inertia redirect
   └─> with flash message

8. React component receives props update
   └─> Re-renders with new order in list
```

## Game Flow Overview

### Core Gameplay Loop

```
┌─────────────────────────────────────────────────────────┐
│  1. ASSESS SITUATION                                     │
│     - Review inventory levels across locations          │
│     - Check alerts (stockouts, expiry, spikes)          │
│     - Analyze pending orders and transfers              │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│  2. MAKE DECISIONS                                       │
│     - Place vendor orders                               │
│     - Initiate internal transfers                       │
│     - Adjust inventory policies                         │
│     - Respond to spike events                           │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│  3. ADVANCE TIME                                         │
│     - Click "Advance Day" button                        │
│     - Simulation processes all events                   │
│     - Deliveries arrive, spikes occur, costs applied    │
└─────────────────────────────────────────────────────────┘
                        ↓
                  (Loop back to step 1)
```

## Key Concepts

### 1. Multi-Location Inventory
Players manage stock across three locations with different storage capacities:
- **Moonshine HQ (Roastery)**: 5,000 units - Central warehouse
- **Uptown Kiosk**: 500 units - Small retail location
- **Lakeside Cafe**: 1,200 units - Medium retail location

### 2. Vendor Management
Four vendors with different characteristics:
- **BeanCo Global**: Premium, reliable, standard speed
- **RapidSupplies**: Fast but expensive
- **Dairy Direct**: Milk specialist, very reliable
- **ValueBulk**: Cheapest but slow and unreliable

### 3. Multi-Hop Logistics
Orders can route through multiple locations when direct routes aren't available:
```
Vendor → HQ → Kiosk (2 legs)
Total delivery time = Lead time + Transit time (leg 1) + Transit time (leg 2)
```

### 4. Dynamic Events (Spikes)
Random events add challenge:
- **Demand Spike**: Increased consumption (1.5x - 3x)
- **Vendor Delay**: Extended lead times (+2 to +4 days)
- **Price Spike**: Increased costs (1.3x - 2x)
- **Equipment Breakdown**: Reduced storage (20% - 40%)
- **Blizzard**: Route delays (+1 to +2 days per leg)

### 5. Resource Management
Players must balance:
- **Cash**: Starting with $5,000, all actions cost money
- **Storage**: Limited capacity at each location
- **Time**: Lead times and delivery schedules
- **Perishability**: Some items expire

### 6. AI Decision Support
Prism AI provides:
- Inventory recommendations
- Risk assessments
- Natural language explanations
- Optimization suggestions

## Testing

### Backend Tests
```bash
# Run all tests
composer test

# Run specific test
php artisan test --filter=OrderTest

# With coverage
php artisan test --coverage
```

### Frontend Tests
```bash
# Run all tests
npm run test

# Watch mode
npm run test:watch

# Coverage
npm run test:coverage
```

## Deployment

### Production Build
```bash
# Build frontend
npm run build
npm run build:ssr

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force
```

### Environment Variables
See `.env.example` for required environment variables.

## Contributing

### Code Style
- **PHP**: Follow PSR-12, use Laravel Pint
- **TypeScript**: Follow Airbnb style guide, use Prettier
- **Commits**: Use conventional commits (feat:, fix:, docs:, etc.)

### Pull Request Process
1. Create feature branch from `master`
2. Write tests for new features
3. Ensure all tests pass
4. Run linters and formatters
5. Submit PR with clear description
6. Address review feedback

## Troubleshooting

### Common Issues

**Inertia version mismatch**:
```bash
php artisan inertia:clear
npm run build
```

**Database migration errors**:
```bash
php artisan migrate:fresh --seed
```

**Node modules issues**:
```bash
rm -rf node_modules package-lock.json
npm install
```

**Type errors**:
```bash
npm run types
# Fix any TypeScript errors
```

## Support

- **Documentation**: This repository
- **Issues**: GitHub Issues
- **Email**: [Project maintainer email]

## License

[License information]

## Changelog

See [CHANGELOG.md](../CHANGELOG.md) for version history.

---

**Last Updated**: 2026-01-20

For detailed information on specific topics, navigate to the respective documentation sections using the links above.
