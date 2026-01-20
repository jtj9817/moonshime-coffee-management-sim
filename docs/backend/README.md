# Backend Documentation

This directory contains comprehensive documentation for the Laravel backend of the Moonshine Coffee Management Sim.

## Overview

The backend is built with **Laravel 12** and **PHP 8.2+**, using **PostgreSQL** as the database. It follows Laravel best practices and conventions, leveraging modern PHP features and Laravel's ecosystem.

## Architecture

The backend follows a **Service-Oriented Architecture** with clear separation of concerns:

- **Models**: Eloquent models representing database entities
- **Controllers**: HTTP request handlers following RESTful conventions
- **Services**: Business logic and complex operations
- **Events & Listeners**: Event-driven architecture for game mechanics
- **State Machines**: Managing order and transfer state transitions
- **DTOs**: Data Transfer Objects for type-safe data handling
- **Observers**: Model lifecycle hooks

## Tech Stack

- **Framework**: Laravel 12
- **Language**: PHP 8.2+
- **Database**: PostgreSQL
- **ORM**: Eloquent
- **Frontend Bridge**: Inertia.js 2.0
- **Authentication**: Laravel Fortify
- **Routing**: Laravel Wayfinder
- **Queue**: Laravel Queue (for background jobs)

## Documentation Files

1. **[Architecture](./01-architecture.md)** - Overall backend architecture and design patterns
2. **[Models & Database](./02-models-database.md)** - Database schema, models, and relationships
3. **[Controllers & Routes](./03-controllers-routes.md)** - HTTP layer and routing
4. **[Services](./04-services.md)** - Business logic services
5. **[Events & Listeners](./05-events-listeners.md)** - Event-driven architecture
6. **[State Machines](./06-state-machines.md)** - Order and transfer state management
7. **[API Reference](./07-api-reference.md)** - API endpoints and request/response formats

## Key Concepts

### Inertia.js Integration

The backend uses Inertia.js to bridge Laravel and React, providing a seamless SPA experience without building a separate API. Controllers return Inertia responses with typed props that are consumed by React components.

### Event-Driven Game Mechanics

The simulation uses Laravel's event system extensively:
- Time advancement triggers multiple events
- Order deliveries trigger inventory updates
- Spike events trigger alerts and effects
- State transitions emit events for side effects

### Service Layer

Complex business logic is encapsulated in service classes:
- `SimulationService` - Core game simulation logic
- `OrderService` - Order placement and management
- `LogisticsService` - Multi-hop routing and shipments
- `InventoryManagementService` - AI-powered inventory insights
- `PrismAiService` - AI integration for advisories

### State Machines

Orders and transfers use state machine patterns for managing their lifecycle, ensuring valid transitions and triggering appropriate side effects.

## Development Workflow

```bash
# Start development server
php artisan serve

# Run migrations
php artisan migrate

# Run tests
php artisan test

# Run linter
composer lint

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Best Practices

1. **Type Hints**: Always use PHP type hints for parameters and return types
2. **Form Requests**: Use FormRequest classes for validation
3. **Eager Loading**: Prevent N+1 queries with eager loading
4. **Service Classes**: Keep controllers thin, move logic to services
5. **Events**: Use events for cross-cutting concerns
6. **DTOs**: Use DTOs for complex data structures
7. **Transactions**: Wrap multi-step operations in database transactions
8. **Policies**: Use authorization policies for access control

## Testing

The backend uses **PHPUnit/Pest** for testing:
- Feature tests for HTTP endpoints
- Unit tests for services and models
- Database factories for test data
- RefreshDatabase trait for test isolation

## Related Documentation

- [Domain Logic Documentation](../domain/)
- [Frontend Documentation](../frontend/)
- [Technical Design Document](../technical-design-document.md)
