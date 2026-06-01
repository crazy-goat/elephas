# Roadmap

Planned features and milestone timeline for Elephas.

| Milestone | Status | Target |
|-----------|--------|--------|
| [v0.1.0 – Foundation](#v010--foundation) | ✅ Released | 2026-06-01 |
| [v0.2.0 – Backend + Batch](#v020--backend--batch) | ✅ Released | 2026-06-01 |
| [v0.3.0 – Client API](#v030--client-api) | ✅ Released | 2026-06-01 |
| [v0.4.0 – Polish](#v040--polish) | 🚧 In progress | 2026-09-30 |
| [v0.5.0 – Extension Backend + Query API](#v050--extension-backend--query-api) | 📋 Planned | TBD |
| [v0.6.0 – Performance + Tooling](#v060--performance--tooling) | 📋 Planned | TBD |

---

## v0.1.0 – Foundation ✅

**Released: 2026-06-01**

Foundation of the project — tooling, CI/CD, core data types, and scaffolding.

- [x] Project scaffold with PSR-4 autoloading, PHP 8.2+ requirement, ext-ffi dependency
- [x] `.gitignore` and `.editorconfig`
- [x] Docker dev environment with PHP 8.2 CLI and TigerBeetle 0.17.4
- [x] PHP-CS-Fixer, PHPStan (level 8), and Rector configuration
- [x] CI lint job (cs-fixer + phpstan + rector) with push/pr triggers
- [x] CI tests job with PHP 8.2–8.4 matrix + TigerBeetle service container
- [x] Composer scripts: `test`, `test-unit`, `test-functional`, `lint`, `lint-fix`
- [x] Pre-push Git hook via `composer install`/`composer update`
- [x] Functional test runner (`bin/run-functional-tests.sh`)
- [x] `Uint128` — full 128-bit unsigned integer implementation
- [x] `Id` (ULID) — monotonic ID generation with Crockford Base32 encoding
- [x] Backed enums: `Operation`, `AccountFlags`, `TransferFlags`, `AccountFilterFlags`, `QueryFilterFlags`
- [x] Status enums: `CreateAccountStatus`, `CreateTransferStatus`, `PacketStatus`, `InitStatus`, `ClientStatus`
- [x] Exception hierarchy with `ElephasExceptionInterface`

## v0.2.0 – Backend + Batch ✅

**Released: 2026-06-01**

Binary protocol, batch classes, and the FFI backend layer.

- [x] `BinaryHelper` — pack/unpack for all TigerBeetle structs
- [x] `Packet` — async callback wrapper with busy-wait synchronization
- [x] `BackendInterface` — transport contract (`submit`, `close`)
- [x] `AbstractBackend` — Template Method pattern with validation and lifecycle
- [x] `AccountBatch` — typed getters/setters and `fromBuffer` factory
- [x] `TransferBatch` — typed getters/setters and `fromBuffer` factory
- [x] `IdBatch` — batch of 128-bit IDs
- [x] `CreateAccountResultBatch`, `CreateTransferResultBatch` — result batches
- [x] `AccountFilterBatch`, `AccountBalanceBatch`, `QueryFilterBatch` — filter batches
- [x] `FfiBackend` — FFI-based backend using `tb_client` shared library
- [x] `NativeClient` — low-level FFI wrapper around `tb_client` C API

## v0.3.0 – Client API ✅

**Released: 2026-06-01**

All six core TigerBeetle operations exposed through the `Client` class.

- [x] `Client::__construct` + `Client::close` — init/deinit wrapper
- [x] `Client::createAccounts` — `AccountBatch` → `CreateAccountResultBatch`
- [x] `Client::createTransfers` — `TransferBatch` → `CreateTransferResultBatch`
- [x] `Client::lookupAccounts` — batch of IDs → `AccountBatch`
- [x] `Client::lookupTransfers` — batch of IDs → `TransferBatch`
- [x] `Client::getAccountTransfers` — `AccountFilterBatch` → `TransferBatch`
- [x] `Client::getAccountBalances` — `AccountFilterBatch` → `AccountBalanceBatch`

## v0.4.0 – Polish 🚧

**Target: 2026-09-30**

Documentation, license, CI/CD polish, and developer experience improvements.

### Completed

- [x] `BackendFactory` — auto-detect available backend implementation
- [x] `README.md` — installation guide, quickstart, API reference, and development docs
- [x] `LICENSE` — MIT license
- [x] `bin/build-tb-client.sh` — build script for `tb_client` native library
- [x] CI/CD release workflow — builds and attaches native libraries to GitHub Releases
- [x] `CHANGELOG.md` — Keep a Changelog format
- [x] `CONTRIBUTING.md` — coding standards, PR flow, dev setup

### Remaining

- [ ] `ROADMAP.md` — planned features and milestone timeline **(this document)**
- [ ] `SECURITY.md` + issue/PR templates — community health files

## v0.5.0 – Extension Backend + Query API 📋

**Status: Planned**

Native PHP extension, query operations, and improved error handling.

- [ ] `ExtensionBackend` — native PHP extension (`ext-elephas`) backend
- [ ] `BackendFactory::isExtensionAvailable()` — detection for `ext-elephas`
- [ ] `Client::queryAccounts` — `QueryFilterBatch` → `AccountBatch`
- [ ] `Client::queryTransfers` — `QueryFilterBatch` → `TransferBatch`
- [ ] `ChangeEventsFilterBatch` — change events support
- [ ] `Client::getAccountBalances` with `QueryFilterFlags`
- [ ] Streaming/batched result iteration for large result sets
- [ ] Improved error messages with TigerBeetle status descriptions
- [ ] `ext-elephas` PHP extension (separate repository)

## v0.6.0 – Performance + Tooling 📋

**Status: Planned**

Performance optimizations, metrics, and developer tooling.

- [ ] Connection pooling / multiplexing for high throughput
- [ ] Batch operation metrics (timing, size, count)
- [ ] `\Stringable` support on `Uint128` for PHP 8.2+
- [ ] Symfony bundle / Laravel package (separate repositories)
- [ ] Static analysis stub generation for `tb_client` FFI
- [ ] Fuzzing / property-based testing for binary packing
- [ ] Benchmark suite (`phpbench`)
- [ ] Automated Packagist publishing on release
- [ ] Performance regression tracking in CI

## Future Considerations

Beyond v0.6.0, the following may be explored based on community demand:

- **Async/non-blocking API** using PHP fibers or Revolt
- **DTO objects** as an alternative to batch-style API
- **TigerBeetle v0.18+** compatibility upgrades
- **Windows support** for the native library build
- **WebAssembly backend** for in-browser or edge runtimes
