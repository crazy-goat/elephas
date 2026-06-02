# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- CI workflow now executes both unit and functional PHPUnit suites, with distinct steps for each (#107)
- CI provisions a version-pinned (0.17.4) `tb_client` native library before running functional tests, ensuring FFI-backed tests no longer silently skip in CI (#108)

### Changed
- Replaced `assert()` calls with explicit exception-throwing validation at public and native boundaries so that validation cannot be silently disabled by PHP assertion settings (#121)

### Fixed
- All batch `fromBuffer()` factories now reject malformed buffers whose size is not an exact multiple of the expected struct size, preventing partial-record deserialization (#113)

### Changed
- `CreateAccountResult` and `CreateTransferResult` no longer expose a synthetic `getId()` derived from the TigerBeetle-assigned timestamp. Both classes now provide `getTimestamp(): int` reflecting the actual timestamp returned by TigerBeetle for each created or rejected event. This aligns the public API with the native TigerBeetle `tb_create_account_result_t` / `tb_create_transfer_result_t` struct contract (TB 0.17.x) where each result carries a `uint64_t timestamp` and a `uint32_t status` (#111)
- `NativeClient` request completion no longer confuses `TB_PACKET_OK` (status 0) with an incomplete/pending packet. A sentinel status value (`0xFFFFFFFF`) is now used to track the pending state, allowing status 0 to be correctly interpreted as a successful response (#109)
- `NativeClient::submit()` now retains a PHP reference to the FFI data buffer for the full native request lifetime, preventing a potential use-after-free when the CData backing the request payload is garbage-collected while `tb_client` still holds the raw pointer (#110)

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
