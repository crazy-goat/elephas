# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Documented synchronous wait model in `NativeClient`: class-level PHPDoc explains the polling approach, thread-safety limitations, and CPU usage characteristics (#123)
- `NativeClient::POLL_INTERVAL_USECONDS` constant (100 µs) makes the polling interval explicit and configurable (#123)
- `NativeClient::waitInterval()` method extracted from `pollForCompletion()` for testability and subclass override (#123)
- Unit tests verifying polling interval constant values, `waitInterval()` sleep duration, and `pollForCompletion()` deadline enforcement (#123)
- `BackendFactory::create()` now accepts an optional `$libPath` parameter for specifying an explicit trusted native library path (#127)
- FFI security documentation in README covering trust model, loading precedence, and best practices (#127)
- Native library loading precedence documented in ARCHITECTURE.md, including security rationale for omitting system-wide paths (#127)
- Documented create operation result semantics in README: positional correspondence, `isCreated()`/`getStatus()`/`getTimestamp()`, partial failure, linked events chain, and `LINKED_EVENT_CHAIN_OPEN`/`LINKED_EVENT_FAILED` behaviour (#136)
- Documentation consistency tests verifying the presence of create-result sections and key terms in README (#136)
- Optional GMP-accelerated `Uint128::fromString()` and `Uint128::toString()` when `ext-gmp` is available, providing significantly faster decimal parsing and formatting for high-volume conversion workloads (#126)

### Changed
- CI TigerBeetle containers no longer use `--privileged`. The `format` command runs with no special capabilities; the `start` command disables only the seccomp profile (`--security-opt seccomp=unconfined`) and grants `--cap-add=IPC_LOCK,SYS_RAWIO` — the documented minimum for TigerBeetle. This reduces the CI attack surface. (#130)
- Optional BCMath-accelerated `Uint128::fromString()` and `Uint128::toString()` when `ext-bcmath` is available, providing a secondary acceleration path when GMP is not installed (#126)
- Transparent fallback: `Uint128` automatically selects GMP → BCMath → pure-PHP based on extension availability, with consistent results across all paths (#126)
- Unit tests verifying cross-implementation consistency, byte-level round-trips, overflow detection, and factory method agreement across all conversion paths (#126)
- `Account` DTO fields `debitsPending`, `debitsPosted`, `creditsPending`, `creditsPosted` changed from `int` to `Uint128` to match TigerBeetle 128-bit ranges (#118)
- `Transfer` DTO field `amount` changed from `int` to `Uint128` to match TigerBeetle 128-bit range (#118)
- Removed bogus fields `debitsReserved`, `creditsReserved`, `debitsAccepted`, `creditsAccepted` from `Account` DTO — these fields do not exist in the native `tb_account_t` struct (#118)

### Added
- Unit tests for `AccountBalance` DTO class covering constructor, getters, default values, readonly nature, zero values, max timestamp, and edge cases (#176)
- `Uint128` now implements `\Stringable` interface with `__toString()` delegating to `toString()`, enabling string interpolation and `string|Stringable` type hint usage (#168)
- `Client` lifecycle, concurrency, and `close()` behaviour documented in README, including long-running process considerations and thread-safety guidance (#137)
- Unit tests in `DocumentationTest` verifying the presence of lifecycle/concurrency/timeout sections in README (#137)
- `UnknownStatusException` for clear, actionable error messages when native library returns an unrecognized enum status value during response parsing (#133)
- `CreateAccountResultBatch::getResult()` and `CreateTransferResultBatch::getResult()` now throw `UnknownStatusException` instead of raw `\ValueError` when a status value is unknown (#133)
- `NativeClient` initialization now handles unknown `InitStatus` values gracefully, throwing `InitializationException` instead of `\ValueError` (#133)
- Unit tests covering unknown enum status handling for all response batch types, malformed buffer edge cases, and clear error message verification (#133)
- CI generates source coverage reports (clover XML) during unit test execution and enforces a minimum 80% element coverage threshold (#135)
- `bin/check-coverage.php` script to validate coverage percentage against a configurable threshold in CI (#135)
- Fully implemented `ChangeEventsFilterBatch` with `setAccountId()`/`getAccountId()` methods for filtering change events by account (#117)
- CI workflow now executes both unit and functional PHPUnit suites, with distinct steps for each (#107)
- Unit tests for `QueryFilter` DTO class covering constructor, getters, default values, readonly nature, and max value edge cases (#175)
- CI provisions a version-pinned (0.17.4) `tb_client` native library before running functional tests, ensuring FFI-backed tests no longer silently skip in CI (#108)
- `AccountBatch::isFound()` and `TransferBatch::isFound()` methods for detecting missing records in lookup results (#112)
- Lookup behaviour (ordering, missing-record zeroed struct) documented in README and `ClientInterface` docblocks (#112)
- `NativeClient` lifecycle and failure-mode tests covering initialisation success/failure, request completion, native error statuses, timeout, and deinitialisation idempotency (#134)
- `CrazyGoat\Elephas\Internal\BinaryRange` helper with `assertUint8/16/32/64` static checks (#120)
- `CrazyGoat\Elephas\Exception\InvalidBatchCursorException` thrown by batch getters and setters when the cursor position is outside the populated range (#119)
- `Client::withTimeout()` factory and `RequestTimeoutException` for configurable, domain-specific request timeouts (#122)
- `NativeClient` accepts a `$timeoutSeconds` constructor parameter forwarded through `BackendFactory` and `FfiBackend` (#122)
- `Client::queryAccounts()` and `Client::queryTransfers()` now implement the full `QueryFilter` round-trip (pack `QueryFilter` → submit `QUERY_ACCOUNTS`/`QUERY_TRANSFERS` → decode `AccountBatch`/`TransferBatch`), resolving the misleading "not implemented" public contract (#114)

### Changed
- Native library auto-detection now only searches project-local paths (`resources/lib/`), removing system-wide fallbacks (`/usr/local/lib`, `/usr/lib`) to prevent accidental loading of untrusted or version-mismatched libraries via FFI (#127)
- `NativeClient::detectLibraryPath()` no longer searches system directories; users requiring a custom path must provide an explicit `$libPath` (#127)

### Changed
- Updated `ROADMAP.md` — moved `ROADMAP.md` and community health files (`.github/SECURITY.md`, issue/PR templates) from "Remaining" to "Completed" in the v0.4.0 milestone, reflecting their actual implementation status (#169)
- Replaced `assert()` calls with explicit exception-throwing validation at public and native boundaries so that validation cannot be silently disabled by PHP assertion settings (#121)
- `NativeClient` FFI calls (`tb_client_init`, `tb_client_submit`, `tb_client_deinit`) extracted to overridable protected methods, enabling controlled test doubles without a real native library (#134)
- `CreateAccountResult` and `CreateTransferResult` no longer expose a synthetic `getId()` derived from the TigerBeetle-assigned timestamp. Both classes now provide `getTimestamp(): int` reflecting the actual timestamp returned by TigerBeetle for each created or rejected event. This aligns the public API with the native TigerBeetle `tb_create_account_result_t` / `tb_create_transfer_result_t` struct contract (TB 0.17.x) where each result carries a `uint64_t timestamp` and a `uint32_t status` (#111)
- `NativeClient` request completion no longer confuses `TB_PACKET_OK` (status 0) with an incomplete/pending packet. A sentinel status value (`0xFFFFFFFF`) is now used to track the pending state, allowing status 0 to be correctly interpreted as a successful response (#109)
- `NativeClient::submit()` now retains a PHP reference to the FFI data buffer for the full native request lifetime, preventing a potential use-after-free when the CData backing the request payload is garbage-collected while `tb_client` still holds the raw pointer (#110)

### Removed
- Removed unused `CrazyGoat\Elephas\Internal\Packet` class and its test (`PacketTest`) — the native request flow uses `tb_packet_t` directly via FFI, making the PHP-level Packet abstraction redundant (#124)

### Fixed
- Corrected `Uint128::toHex()` documentation in README (no `0x` prefix) and `Id::fromString()` return type (now `Uint128` instead of `string`) (#116)
- PHPStan memory limit set to 512M in `composer lint` script to prevent out-of-memory failures during static analysis (#138)
- Removed stale `TODO: implement` comments from `Transfer`, `Account`, and `ChangeEventsFilterBatch` public DTO classes (#117)
- All batch `fromBuffer()` factories now reject malformed buffers whose size is not an exact multiple of the expected struct size, preventing partial-record deserialization (#113)
- Batch getters and setters on `AccountBatch`, `TransferBatch`, `IdBatch`, `AccountFilterBatch`, `QueryFilterBatch`, `AccountBalanceBatch`, `CreateAccountResultBatch`, and `CreateTransferResultBatch` now fail fast with a dedicated `InvalidBatchCursorException` when called before `add()` (or on a buffer created from an empty response), instead of silently writing into or reading from the pre-allocated buffer while the logical length remains zero (#119)
- Integer setters on `AccountBatch`, `TransferBatch`, `QueryFilterBatch`, and `AccountFilterBatch` now validate that values fit their declared unsigned width (`uint8`/`uint16`/`uint32`/`uint64`) before binary packing. Negative or oversized values now raise `IntegerOverflowException` with the offending field name and accepted range, instead of being silently reinterpreted by `pack()` (#120)

## [0.4.0] – Polish – 2026-06-02

### Added
- `.github/SECURITY.md` – security policy and vulnerability reporting instructions (#56)
- `.github/ISSUE_TEMPLATE/bug_report.md` – bug report template (#56)
- `.github/ISSUE_TEMPLATE/feature_request.md` – feature request template (#56)
- `.github/PULL_REQUEST_TEMPLATE.md` – pull request checklist template (#56)
- `BackendFactory` – auto-detect available backend implementation (#48)
- `README.md` – installation guide, quickstart, API reference, and development docs (#51)
- `LICENSE` – MIT license (#52)
- `CHANGELOG.md` – Keep a Changelog format (#53)
- `ROADMAP.md` – milestone timeline and planned features (#54)
- `CONTRIBUTING.md` – development guidelines and PR process (#55)
- `bin/build-tb-client.sh` – build script that compiles the `tb_client` native shared library for the host platform using Zig 0.14.1 and TigerBeetle 0.17.4 (#49)
- CI/CD release workflow – builds `tb_client` for linux/amd64, linux/arm64, macos/amd64, macos/arm64 on tag push `v*` and attaches the shared libraries to a GitHub Release (#50)

### Removed
- Unused `AccountFilter` value object and orphaned `BinaryHelper::packAccountFilter()`/`unpackAccountFilter()` methods (#97)

## [0.3.0] – Client API – 2026-06-01

### Added
- `Client` API: `createAccounts`, `createTransfers`, `lookupAccounts`, `lookupTransfers`, `getAccountTransfers`, `getAccountBalances` (#41, #42, #43, #44, #45, #46, #47)

## [0.2.0] – Backend + Batch – 2026-06-01

### Added
- `BinaryHelper` – pack/unpack for TigerBeetle structs: `Account` (128B), `Transfer` (128B), `AccountFilter` (128B), `AccountBalance` (128B), `QueryFilter` (64B), `CreateAccountResult` (16B), `CreateTransferResult` (16B) (#28, #29, #30)
- `Packet` – final class wrapping `tb_client` async callback with busy-wait synchronization, nullable response, `PacketStatus` enum (#31)
- `BackendInterface` – transport contract (`submit`, `close`) (#32)
- `AbstractBackend` – Template Method pattern with `closed` flag, `ClientClosedException`, `TooMuchDataException` validation, idempotent `close()`, 1 MB default max batch size (#32)
- `AbstractBatch` – buffer-based navigation (`add`, `currentPosition`, `count`, `reset`, `getBuffer`) (#33)
- `AccountBatch` – typed getters/setters and `fromBuffer` factory for `Account` (#34)
- `TransferBatch` – typed getters/setters and `fromBuffer` factory for `Transfer` (#35)
- `IdBatch` – batch of 128-bit IDs (#36)
- Result batches: `CreateAccountResultBatch`, `CreateTransferResultBatch` with index/result accessors (#36)
- Filter batches: `AccountFilterBatch`, `AccountBalanceBatch`, `QueryFilterBatch` (#37)
- `FfiBackend` – FFI-based backend using `tb_client` shared library (#38, #39, #40)
- `NativeClient` – low-level FFI wrapper around `tb_client` C API (#40)

## [0.1.0] – Foundation – 2026-06-01

### Added
- Project scaffold with PSR-4 autoloading, PHP 8.2+ requirement, ext-ffi dependency (#1, #57)
- `.gitignore` and `.editorconfig` (#10)
- Docker dev environment with PHP 8.2 CLI and TigerBeetle 0.17.4 (#12)
- PHP-CS-Fixer, PHPStan, and Rector configuration (#9, #13, #14, #15, #77)
- CI lint job (cs-fixer + phpstan + rector) with push/pr triggers (#16, #71)
- CI tests job with PHP 8.2-8.4 matrix + TigerBeetle service container (#8, #73)
- Pre-push hook via `composer install`/`composer update` (#78)
- Composer scripts: `test`, `test-unit`, `test-functional`, `lint`, `lint-fix` (#78)
- Functional test runner (`bin/run-functional-tests.sh`) (#78)
- `Uint128` – constructors (`fromInt`, `fromString`, `fromBytes`, `zero`), `equals`, `compareTo`, `toBytes`/`fromBytes`, `toInt`, `toFloat`, `toString` (#18, #19, #20, #21, #60, #66, #67, #69)
- `Id` (ULID) – `generate()` with 48-bit timestamp + 80-bit random, monotonicity, Crockford Base32 encoding (#22, #23, #59, #61)
- Backed enums: `Operation`, `AccountFlags`, `TransferFlags` with missing constants (#24, #65)
- Enums: `AccountFilterFlags`, `QueryFilterFlags` with `NONE` constant and `combine()` (#25, #64)
- Status enums: `CreateAccountStatus`, `CreateTransferStatus`, `PacketStatus`, `InitStatus`, `ClientStatus` (#26, #63)
- Exception classes: `ElephasExceptionInterface`, `ConcurrencyException`, `InvalidAddressException`, `NotFoundException`, `ProtocolException`, `TigerBeetleException`, `UnexpectedException`, `UnsupportedPhpVersionException` (#27, #62)
- PHPUnit strict mode (`failOnRisky`, `failOnWarning`, `beStrictAboutOutput`) (#68)
- README with test and lint instructions (#78)

### Changed
- Updated composer.json to align with project specification (#70)
- PHP-CS-Fixer config with `setUnsupportedPhpVersionAllowed` (#77)

### Fixed
- Tests gate job now checks lint results before allowing merge (#74)
- CITest skips when TigerBeetle is not available (#75)
- Branch protection job names aligned with required checks (#58)
