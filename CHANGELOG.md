# Changelog

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
