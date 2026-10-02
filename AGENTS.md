# AGENTS.md

Project commands and specifics for elephas, a PHP client for TigerBeetle. The development
process (issue, worktree, review, PR, merge) is in [docs/workflow.md](docs/workflow.md),
the release process in [docs/release-workflow.md](docs/release-workflow.md).

Everything is written in English (code, comments, docs, commits, issues).

## Layout

| Path | Content |
|---|---|
| `src/` | Library, namespace `CrazyGoat\Elephas\` (`Client`, enums, `Id`, `Uint128/`, `Batch/`, `Backend/`, `Exception/`, `Internal/`) |
| `src/Backend/` | `BackendFactory`, `FfiBackend`, `NativeClient` (PHP FFI binding to `tb_client`) |
| `tests/Unit/` | Unit suite, needs no TigerBeetle |
| `tests/Functional/` | Functional suite, needs a running TigerBeetle (`TIGERBEETLE_ADDRESS`) |
| `bin/` | `build-tb-client.sh`, `run-functional-tests.sh`, `check-coverage.php`, `install-git-hook.php`, worktree scripts |
| `docker/` | `docker-compose.yml` (TigerBeetle 0.17.4 + PHP CLI), `Dockerfile`, `validate.sh` |
| `resources/lib/` | Built `libtb_client.{so,dylib}` per platform (gitignored) |
| `docs/` | `ARCHITECTURE.md`, `ROADMAP.md`, process docs |

Read `docs/ARCHITECTURE.md` before changing the backend or the binary layout of batches.

## Commands

PHP 8.2+ with `ext-ffi` (`ext-gmp` and `ext-bcmath` are optional). CI runs PHP 8.2, 8.3 and 8.4.

```bash
composer install

# Lint: composer validate/audit, php-cs-fixer, phpstan level 8, rector, shellcheck, hadolint (check only)
bin/lint.sh                            # same as composer lint
bin/lint.sh --fix                      # rector and php-cs-fixer fix first, then check (composer lint-fix)

# Unit tests
composer test-unit                      # vendor/bin/phpunit --testsuite=unit
vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage/clover.xml
php bin/check-coverage.php coverage/clover.xml 80   # CI threshold

# Functional tests: starts docker/docker-compose.yml, runs the suite, stops it
composer test-functional
composer test                          # unit + functional

# Native library (needs network; downloads Zig and clones TigerBeetle)
bash bin/build-tb-client.sh
```

The `lint` job runs only `bin/lint.sh`, which includes `composer validate --strict` and `composer audit`.

## TigerBeetle and FFI

- The client talks to TigerBeetle through the native `tb_client` library loaded with PHP FFI.
  TigerBeetle is pinned to 0.17.4 and Zig to 0.14.1 (`TB_VERSION`, `ZIG_VERSION` in
  `.github/workflows/tests.yaml`; the image tag in `docker/docker-compose.yml`).
- `NativeClient::detectLibraryPath()` looks only in `resources/lib/<platform>/`. System paths are
  deliberately not searched, because FFI runs native code in the PHP process.
  See `docs/ARCHITECTURE.md`.
- The TigerBeetle containers in CI use `--privileged` because of `io_uring` (issue #130).
- Functional tests read the server from `TIGERBEETLE_ADDRESS` (for example `127.0.0.1:3000`).
  `bin/run-functional-tests.sh` sets it from `TIGERBEETLE_PORT` (default `3000`), the host port
  published by `docker/docker-compose.yml`. `bin/worktree.sh` writes a free port for it to
  `.env.worktree`; load it with `set -a && . ./.env.worktree && set +a`.
  `bin/worktree-teardown.sh` stops the stack of the worktree.

## CI

`.github/workflows/tests.yaml` runs on pull requests and on pushes to `main`. The `changes`
job detects documentation-only changes; the `docs` job checks them fast. `lint`, `build-lib`
and the PHP matrix run only for code changes. `tests` and `ci-ok` aggregate the results.
The required check will be `ci-ok` after the ruleset switch; until then `tests` and `lint`.

## Conventions

- PER-CS 2.0 with `declare(strict_types=1)` in every file (`.php-cs-fixer.dist.php`).
  PHPStan level 8, Rector for PHP 8.2.
- Commit scopes: `uint128`, `id`, `batch`, `backend`, `client`, `binary-helper`, `ci`, `docker`,
  `docs`. Example: `fix(backend): handle connection timeout`.
- New code gets tests: unit tests in `tests/Unit/` mirroring `src/`, named `{Class}Test.php`.
- `tests/Unit/TestWorkflowTest.php` and `ReleaseWorkflowTest.php` assert the content of the
  workflow files. Update them together with the workflows.
- Public API changes are documented in `CHANGELOG.md` under `[Unreleased]`.
