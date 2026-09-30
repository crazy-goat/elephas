# Contributing to Elephas

This repository follows the shared guide of all `crazy-goat` repositories:
[CONTRIBUTING.md](https://github.com/crazy-goat/.github/blob/main/CONTRIBUTING.md).
The development process is in [docs/workflow.md](docs/workflow.md), the release process in
[docs/release-workflow.md](docs/release-workflow.md) and the project commands in
[AGENTS.md](AGENTS.md). Below are only the elephas specifics.

## Development Setup

### Prerequisites

- PHP 8.2+
- [Docker](https://docs.docker.com/engine/install/) (for functional tests)
- [Composer](https://getcomposer.org/)

### Docker (recommended)

The repository includes a Docker setup with TigerBeetle and PHP CLI:

```bash
cd docker
docker compose up -d --build

# Enter the PHP container
docker compose exec elephas bash

# Inside the container:
composer install
composer test
```

### Manual Setup

```bash
# Install dependencies
composer install

# Install the pre-push Git hook (opt-in – run manually when you want it)
php bin/install-git-hook.php --force
```

The pre-push hook runs `composer lint` before every push, so you catch issues early.
Use `php bin/install-git-hook.php --uninstall` to remove it later.

## Coding Standards

Elephas follows **PER-CS2x0** with strict typing enabled everywhere.

### PHP-CS-Fixer

Configuration: `.php-cs-fixer.dist.php`

- `@PER-CS2x0` + `@PER-CS2x0:risky`
- `declare_strict_types: true`
- `ordered_imports: true`
- `no_superfluous_phpdoc_tags: true`
- `trailing_comma_in_multiline: [arrays, match, arguments, parameters]`

### PHPStan

Configuration: `phpstan.neon.dist`

- **Level 8** — maximum strictness
- `treatPhpDocTypesAsCertain: false`

### Rector

Configuration: `rector.php`

- PHP 8.2 sets
- `deadCode`, `codeQuality`, `typeDeclarations`

## Testing

We use **PHPUnit 11.x** with two test suites:

| Suite | Command | Requires | Description |
|-------|---------|----------|-------------|
| Unit | `composer test-unit` | None | Pure PHP logic (Uint128, Id, batches, BinaryHelper) |
| Functional | `composer test-functional` | Docker + TigerBeetle | End-to-end operations via FFI |
| All | `composer test` | Docker + TigerBeetle | Both suites |

```bash
# Run all tests (unit + functional)
composer test

# Run only unit tests (no Docker required)
composer test-unit

# Run functional tests (starts Docker, runs tests, stops Docker)
composer test-functional

# Run a specific test file
vendor/bin/phpunit --testsuite=unit tests/Unit/Batch/AccountBatchTest.php
```

### Writing Tests

- Unit tests go in `tests/Unit/` mirroring the `src/` structure.
- Functional tests go in `tests/Functional/`.
- Test classes extend `PHPUnit\Framework\TestCase`.
- Use `declare(strict_types=1)`.
- Naming convention: `{ClassUnderTest}Test.php`.

## Linting

Run all linters in dry-run mode:

```bash
composer lint
```

This runs:
1. `php-cs-fixer fix --dry-run` — code style check
2. `phpstan analyse` — static analysis (level 8)
3. `rector process --dry-run` — upgrade analysis

Auto-fix what can be fixed automatically:

```bash
composer lint-fix
```

This runs:
1. `php-cs-fixer fix` — auto-format
2. `rector process` — auto-upgrade

## Branches, commits and pull requests

Branch names are `type/issue-<N>-<slug>`; commits and PR titles are
[Conventional Commits](https://www.conventionalcommits.org/) with an optional scope
(`uint128`, `id`, `batch`, `backend`, `client`, `binary-helper`, `ci`, `docker`).
Before opening a PR run `composer lint` and `composer test-unit`, and add a line to
`CHANGELOG.md` under `[Unreleased]`. PRs are squash merged once `ci-ok` is green.
