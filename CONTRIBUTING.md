# Contributing to Elephas

Thank you for considering contributing to Elephas! This document outlines the development workflow, coding standards, and pull request process.

## Table of Contents

1. [Development Setup](#development-setup)
2. [Coding Standards](#coding-standards)
3. [Testing](#testing)
4. [Linting](#linting)
5. [Branch Naming](#branch-naming)
6. [Commit Message Format](#commit-message-format)
7. [Pull Request Process](#pull-request-process)
8. [Release Process](#release-process)

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

# Install the pre-push Git hook
php bin/install-git-hook.php
```

The pre-push hook runs `composer lint` before every push, so you catch issues early.

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

## Branch Naming

Branches follow the `type/description` pattern:

| Type | Purpose |
|------|---------|
| `feat/` | New features |
| `fix/` | Bug fixes |
| `docs/` | Documentation changes |
| `refactor/` | Code refactoring |
| `test/` | Adding or updating tests |
| `chore/` | Maintenance, CI, tooling |

Examples:
- `feat/issue-42-client-create-accounts`
- `fix/issue-10-invalid-address-handling`
- `docs/issue-55-contributing-guide`

## Commit Message Format

We follow the **Conventional Commits** specification:

```
type(scope): description

[optional body]

[optional footer]
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | A new feature |
| `fix` | A bug fix |
| `docs` | Documentation only |
| `refactor` | Code change that neither fixes nor adds |
| `test` | Adding or updating tests |
| `chore` | Build process, CI, tooling |

### Scope

The scope is optional but recommended. Use the component name, e.g.:
- `uint128`, `id`, `batch`, `backend`, `client`, `binary-helper`, `ci`, `docker`

### Examples

```
feat(uint128): add fromHex factory method
fix(backend): handle connection timeout
docs(readme): update installation guide
test(batch): add AccountBatch edge cases
```

## Pull Request Process

1. **Create an issue** first for non-trivial changes (unless one already exists).

2. **Create a feature branch** from `main` using the naming convention above.

3. **Make your changes** following the coding standards.

4. **Write or update tests** — all code must be covered.

5. **Run linting locally**:
   ```bash
   composer lint
   ```

6. **Run tests locally** (at least the unit suite):
   ```bash
   composer test-unit
   ```

7. **Commit** using the conventional commit format:
   ```bash
   git commit -m "feat(client): implement createAccounts operation"
   ```

8. **Push** and create a Pull Request against `main`:
   ```bash
   git push origin feat/issue-55-contributing
   ```

9. **PR title** should match the conventional commit format:
   ```
   feat(client): implement createAccounts operation
   ```

10. **PR description** should include:
    - **What** — summary of changes
    - **Why** — motivation or issue reference (e.g. "Closes #42")
    - **How** — implementation notes (if non-obvious)
    - **Checklist:**
      - [ ] Linting passes (`composer lint`)
      - [ ] Tests pass (`composer test-unit && composer test-functional`)
      - [ ] New code is covered by tests

11. **Wait for CI** — the PR must pass all lint and test checks before merge.

12. **Code review** — at least one approval is required. Address all review comments.

13. **Squash merge** into `main` to keep history clean.

## Release Process

Releases are automated via GitHub Actions. When a tag matching `v*` is pushed:

1. The release workflow builds `tb_client` native libraries for all platforms.
2. A GitHub Release is created with the pre-built libraries attached.
3. The release notes are auto-generated from the changelog.

Maintainers handle the release process. Contributors do not need to worry about it.
