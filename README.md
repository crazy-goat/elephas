# Elephas

[![Tests](https://github.com/crazy-goat/elephas/actions/workflows/tests.yaml/badge.svg)](https://github.com/crazy-goat/elephas/actions/workflows/tests.yaml)
[![Release](https://img.shields.io/github/v/release/crazy-goat/elephas)](https://github.com/crazy-goat/elephas/releases)
[![PHP Version](https://img.shields.io/badge/PHP-^8.2-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

<p align="center">
  <img src="assets/elephas-logo.jpg" alt="Elephas logo" width="320">
</p>

PHP client for [TigerBeetle](https://tigerbeetle.com) — a high-performance financial transactions database.

## Requirements

- PHP 8.2+
- `ext-ffi`
- Docker (for functional tests)

Optional extensions (improve performance of Uint128 arithmetic):
- `ext-gmp` (recommended)
- `ext-bcmath`

## Installation

```bash
composer require crazy-goat/elephas
```

A [pre-built native library](https://github.com/crazy-goat/elephas/releases) (`tb_client`) is required at runtime.
Download the archive matching your platform from the latest release and extract it to `resources/lib/`:

```bash
# Example for Linux x86_64 (glibc)
mkdir -p resources/lib/x86_64-linux-gnu
curl -L https://github.com/crazy-goat/elephas/releases/latest/download/libtb_client-x86_64-linux-gnu.so \
  -o resources/lib/x86_64-linux-gnu/libtb_client.so
```

The library is auto-detected at these paths:
- `resources/lib/{platform}/libtb_client.so` (or `.dylib` on macOS)
- `/usr/local/lib/libtb_client.so`
- `/usr/lib/libtb_client.so`

> **Note:** The native library is **not** distributed via Composer. You must download it separately for your target platform.

A Git pre-push hook is installed automatically on `composer install` / `composer update` to run linting before push. To install it manually:

```bash
php bin/install-git-hook.php
```

## Quick Start

```php
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\Uint128\Uint128;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\AccountFilterBatch;

// Connect to TigerBeetle
$client = new Client(Uint128::zero(), '127.0.0.1:3000');

// Create two accounts
$accounts = new AccountBatch(2);
$accounts->add();
$accounts->setId(Uint128::fromString('1'));
$accounts->setLedger(1);
$accounts->setCode(1);

$accounts->add();
$accounts->setId(Uint128::fromString('2'));
$accounts->setLedger(1);
$accounts->setCode(1);

$accountResults = $client->createAccounts($accounts);

// Check results
for ($i = 0, $count = count($accountResults); $i < $count; $i++) {
    $result = $accountResults->getResult();
    if ($result->isCreated()) {
        echo "Account {$result->getId()} created\n";
    }
    $accountResults->next();
}

// Create a transfer from account 1 to account 2
$transfers = new TransferBatch(1);
$transfers->add();
$transfers->setId(Uint128::fromString('1'));
$transfers->setDebitAccountId(Uint128::fromString('1'));
$transfers->setCreditAccountId(Uint128::fromString('2'));
$transfers->setAmount(Uint128::fromInt(1000));
$transfers->setLedger(1);
$transfers->setCode(1);

$transferResults = $client->createTransfers($transfers);

// Lookup accounts
$ids = new IdBatch(2);
$ids->add();
$ids->setId(Uint128::fromString('1'));
$ids->add();
$ids->setId(Uint128::fromString('2'));

$lookedUp = $client->lookupAccounts($ids);

// Get account transfers
$filters = new AccountFilterBatch(1);
$filters->add();
$filters->setAccountId(Uint128::fromString('1'));

$transfers = $client->getAccountTransfers($filters);

// Close the connection
$client->close();
```

## API Reference

### Client

| Method | Description | Returns |
|--------|-------------|---------|
| `__construct(Uint128 $clusterId, string ...$replicaAddresses)` | Connect to a TigerBeetle cluster | — |
| `close(): void` | Disconnect and release resources | — |
| `createAccounts(AccountBatch $batch): CreateAccountResultBatch` | Create accounts | `CreateAccountResultBatch` |
| `createTransfers(TransferBatch $batch): CreateTransferResultBatch` | Create transfers | `CreateTransferResultBatch` |
| `lookupAccounts(IdBatch $ids): AccountBatch` | Lookup accounts by ID | `AccountBatch` |
| `lookupTransfers(IdBatch $ids): TransferBatch` | Lookup transfers by ID | `TransferBatch` |
| `getAccountTransfers(AccountFilterBatch $filter): TransferBatch` | Get transfers for an account | `TransferBatch` |
| `getAccountBalances(AccountFilterBatch $filter): AccountBalanceBatch` | Get account balances | `AccountBalanceBatch` |
| `queryAccounts(QueryFilter $filter): AccountBatch` | Query accounts (not yet implemented) | `AccountBatch` |
| `queryTransfers(QueryFilter $filter): TransferBatch` | Query transfers (not yet implemented) | `TransferBatch` |

### Uint128

| Factory | Description |
|---------|-------------|
| `Uint128::zero(): self` | Returns zero |
| `Uint128::fromInt(int $value): self` | From signed 64-bit integer |
| `Uint128::fromString(string $decimal): self` | From decimal string |
| `Uint128::fromParts(int $low, int $high): self` | From low/high 64-bit parts |
| `Uint128::fromBytes(string $bytes): self` | From 16-byte little-endian binary |
| `Uint128::fromHex(string $hex): self` | From hexadecimal string |

| Method | Description |
|--------|-------------|
| `toInt(): int` | Convert to signed 64-bit integer |
| `toFloat(): float` | Convert to float |
| `toString(): string` | Convert to decimal string |
| `toHex(): string` | Convert to hex string (with `0x` prefix) |
| `toBytes(): string` | Convert to 16-byte little-endian binary |
| `toArray(): array{low: int, high: int}` | Convert to low/high parts |
| `equals(self $other): bool` | Equality check |
| `compareTo(self $other): int` | Comparison (-1, 0, 1) |
| `isZero(): bool` | Check if zero |

### Id (ULID)

| Method | Description |
|--------|-------------|
| `Id::generate(): Uint128` | Generate a monotonic ULID |
| `Id::toString(Uint128 $id): string` | Encode ULID to Crockford Base32 |
| `Id::fromString(string $ulid): string` | Parse Crockford Base32 to ULID |
| `Id::extractTimestamp(Uint128 $id): int` | Extract millisecond timestamp |
| `Id::extractRandom(Uint128 $id): string` | Extract random bytes |

### Batch Classes

All batch classes extend `AbstractBatch` and implement `\Countable`. They are used to pack multiple values into a single request.

| Batch class | Struct size | Mutable | Description |
|-------------|-------------|---------|-------------|
| `AccountBatch` | 128 bytes | Yes | Build/lookup accounts |
| `TransferBatch` | 128 bytes | Yes | Build/lookup transfers |
| `IdBatch` | 16 bytes | Yes | Batch of 128-bit IDs |
| `AccountFilterBatch` | 128 bytes | Yes | Account filter parameters |
| `AccountBalanceBatch` | 128 bytes | No (read-only) | Account balance results |
| `CreateAccountResultBatch` | 16 bytes | No (read-only) | Account creation results |
| `CreateTransferResultBatch` | 16 bytes | No (read-only) | Transfer creation results |
| `QueryFilterBatch` | 64 bytes | Yes | Query filter parameters |
| `ChangeEventsFilterBatch` | 16 bytes | Yes | Change events stub (not yet implemented) |

**Common methods:** `add()`, `next(): bool`, `prev(): bool`, `rewind(): void`, `count(): int`, `getLength(): int`, `getCapacity(): int`

### Enums

| Enum | Values |
|------|--------|
| `Operation` | `PULSE`, `CREATE_ACCOUNTS`, `CREATE_TRANSFERS`, `LOOKUP_ACCOUNTS`, `LOOKUP_TRANSFERS`, `GET_ACCOUNT_TRANSFERS`, `GET_ACCOUNT_BALANCES`, `QUERY_ACCOUNTS`, `QUERY_TRANSFERS` |
| `AccountFlags` | `NONE`, `LINKED`, `DEBITS_MUST_NOT_EXCEED_CREDITS`, `CREDITS_MUST_NOT_EXCEED_DEBITS`, `HISTORY`, `IMPORTED`, `CLOSED`, `ZERO_VALUE_TRANSFERS` |
| `TransferFlags` | `NONE`, `LINKED`, `PENDING`, `POST_PENDING_TRANSFER`, `VOID_PENDING_TRANSFER`, `BALANCING_DEBIT`, `BALANCING_CREDIT`, `CLOSING_DEBIT`, `CLOSING_CREDIT`, `IMPORTED`, `ZERO_VALUE_TRANSFERS` |
| `AccountFilterFlags` | `NONE`, `DEBITS`, `CREDITS`, `REVERSED` |
| `CreateAccountStatus` | `CREATED` + 27 error codes |
| `CreateTransferStatus` | `CREATED` + 36 error codes |
| `InitStatus` | `SUCCESS`, `UNEXPECTED`, `OUT_OF_MEMORY`, `INVALID_ADDRESS`, `SYSTEM_RESOURCES`, `NETWORK_SUBSYSTEM` |
| `PacketStatus` | `OK`, `TOO_MUCH_DATA`, `INVALID_OPERATION`, `INVALID_DATA_SIZE`, `ZERO_ADDRESS`, `ZERO_CLUSTER_ID`, `CONCURRENCY_MAX_EXCEEDED` |

### Exceptions

All exceptions extend `\RuntimeException` and implement `ElephasExceptionInterface`.

| Exception | Description |
|-----------|-------------|
| `ClientClosedException` | Operation on a closed client |
| `ClientEvictedException` | Client was evicted by TigerBeetle |
| `InitializationException` | Failed to initialize native client |
| `IntegerOverflowException` | Uint128 overflow on conversion |
| `RequestException` | Request failed with error status |
| `TooMuchDataException` | Batch exceeds max size |
| `ClientReleaseException` | Failed to release native resources |

## Development

### Docker (recommended)

The repository includes a Docker setup with TigerBeetle and PHP CLI:

```bash
# Start containers
cd docker && docker compose up -d

# Enter the PHP container
docker compose exec elephas bash

# Inside the container:
composer install
composer test
```

### Running Tests

```bash
# Run all tests (unit + functional)
composer test

# Run only unit tests (no Docker required)
composer test-unit

# Run functional tests (starts Docker, runs tests, stops Docker)
composer test-functional
```

### Linting

```bash
# Check code style (PHP-CS-Fixer, PHPStan, Rector)
composer lint

# Auto-fix code style (PHP-CS-Fixer + Rector)
composer lint-fix
```

## Architecture

A native shared library (`tb_client`) communicates with TigerBeetle via FFI. The `NativeClient` wraps the C API,
`Packet` handles callback synchronization, and high-level batch classes pack/unpack binary data.

For a detailed architecture overview, see [ARCHITECTURE.md](ARCHITECTURE.md) (in Polish).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

Elephas is open-source software released under the [MIT License](LICENSE).
