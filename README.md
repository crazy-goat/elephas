# Elephas

PHP client for TigerBeetle – high-performance financial transactions database.

## Running Tests

```bash
# Run all tests (unit + functional)
composer test

# Run only unit tests (no Docker required)
composer test-unit

# Run functional tests (starts Docker, runs tests, stops Docker)
composer test-functional
```

## Linting

```bash
# Check code style (PHP-CS-Fixer, PHPStan, Rector)
composer lint

# Auto-fix code style (PHP-CS-Fixer + Rector)
composer lint-fix
```

## Requirements

- PHP 8.2+
- `ext-ffi`
- Docker (for functional tests)
