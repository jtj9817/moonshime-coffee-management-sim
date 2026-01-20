---
trigger: always_on
---

### Building & Running
The project uses **Laravel Sail** for a containerized environment (Docker). To use the sail command, use `php artisan sail --args=`.

```bash
# Start the environment (Backend + Database)
php artisan sail --args=up

# Frontend Dev Server (HMR)
php artisan sail --args=pnpm --args=dev

# Stop the environment
php artisan sail --args=stop
```

### Testing
Tests are written using **Pest** and should be run within the Sail environment.

```bash
# Run all tests
php artisan sail --args=pest

# Run tests with coverage
php artisan sail --args=pest --args=--coverage
```

```bash
# PHP Linting (Pint)
php artisan sail --args=pint

# JS/TS Linting & Formatting
php artisan sail --args=pnpm --args=lint
php artisan sail --args=pnpm --args=format
php artisan sail --args=pnpm --args=types
```
