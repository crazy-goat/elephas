# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `BackendFactory` – auto-detect available backend implementation (#48)

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
