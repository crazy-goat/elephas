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
# Linux x86_64 (glibc)
mkdir -p resources/lib/x86_64-linux-gnu
curl -L https://github.com/crazy-goat/elephas/releases/latest/download/libtb_client-x86_64-linux-gnu.so \
  -o resources/lib/x86_64-linux-gnu/libtb_client.so

# Linux ARM64 (glibc) — e.g. Graviton, Raspberry Pi 4/5 with 64-bit OS
# mkdir -p resources/lib/aarch64-linux-gnu
# curl -L https://github.com/crazy-goat/elephas/releases/latest/download/libtb_client-aarch64-linux-gnu.so \
#   -o resources/lib/aarch64-linux-gnu/libtb_client.so

# macOS x86_64 (Intel)
# mkdir -p resources/lib/x86_64-macos
# curl -L https://github.com/crazy-goat/elephas/releases/latest/download/libtb_client-x86_64-macos.dylib \
#   -o resources/lib/x86_64-macos/libtb_client.dylib

# macOS ARM64 (Apple Silicon)
# mkdir -p resources/lib/aarch64-macos
# curl -L https://github.com/crazy-goat/elephas/releases/latest/download/libtb_client-aarch64-macos.dylib \
#   -o resources/lib/aarch64-macos/libtb_client.dylib
```

The library is auto-detected at these project-local paths:
- `resources/lib/{platform-dir}/libtb_client.so` (or `.dylib` on macOS)

Where `{platform-dir}` is one of:
- `x86_64-linux-gnu` — Linux x86_64 (glibc)
- `aarch64-linux-gnu` — Linux ARM64 (glibc)
- `x86_64-macos` — macOS Intel
- `aarch64-macos` — macOS Apple Silicon

> **Note:** System-wide paths (`/usr/local/lib`, `/usr/lib`, etc.) are **not** searched for security reasons — see the [FFI Security](#ffi-security) section below. If you need a custom location, use the `$libPath` parameter of `BackendFactory::create()`.

> **Note:** The native library is **not** distributed via Composer. You must download it separately for your target platform.

A Git pre-push hook is available to run linting before push. It is **not** installed automatically – you need to opt in:

```bash
# Install the pre-push hook (prompts before overwriting existing hooks)
php bin/install-git-hook.php

# Force overwrite (backs up any existing hook)
php bin/install-git-hook.php --force

# Remove the installed hook
php bin/install-git-hook.php --uninstall
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

## Client Lifecycle and Concurrency

### Creating and Closing a Client

A `Client` instance represents a connection to a TigerBeetle cluster. It holds native
resources (FFI-backed C library), which **must** be released explicitly via `close()`:

```php
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\Uint128\Uint128;

$client = new Client(Uint128::zero(), '127.0.0.1:3000');

// Use the client…
$accounts = $client->lookupAccounts(/* … */);

// Release native resources
$client->close();
```

After `close()` is called, any further operation on the client throws
`CrazyGoat\Elephas\Exception\ClientClosedException`. Calling `close()` multiple
times is safe – the second and subsequent calls are no-ops.

### When to Close

- **Short-lived scripts** (e.g. CLI commands, cron jobs): close the client when
  you are done with all operations. The native library releases internal memory,
  packet pools, and I/O resources.
- **Long-running processes** (e.g. PHP-FPM, RoadRunner, Swoole workers): create
  one client at worker start and re-use it for the lifetime of the worker. Close
  it during shutdown (e.g. in a `register_shutdown_function` callback).
- **Unit / functional tests**: close the client in `tearDown()` to avoid leaking
  native resources between test cases.

> **Note:** PHP's `ext-ffi` does **not** automatically release native handles
> when the wrapping object goes out of scope. Always call `close()` or use a
> `try`/`finally` block.

### Concurrency

The client is **not** designed for concurrent use. PHP applications typically
use a single-threaded request-response model (e.g. PHP-FPM), where this is not
a limitation.

If you use a multi-threaded runtime (e.g. `ext-parallel`), each thread **must**
create its own `Client` instance. Sharing a single client across threads is
**not safe** and may lead to undefined behaviour in the native library.

## API Reference

### Client

| Method | Description | Returns |
|--------|-------------|---------|
| `__construct(Uint128 $clusterId, string ...$replicaAddresses)` | Connect to a TigerBeetle cluster | — |
| `Client::withTimeout(Uint128 $clusterId, ?float $timeoutSeconds, string ...$replicaAddresses)` | Connect with a custom request timeout | `Client` |
| `close(): void` | Disconnect and release resources | — |
| `createAccounts(AccountBatch $batch): CreateAccountResultBatch` | Create accounts | `CreateAccountResultBatch` |
| `createTransfers(TransferBatch $batch): CreateTransferResultBatch` | Create transfers | `CreateTransferResultBatch` |
| `lookupAccounts(IdBatch $ids): AccountBatch` | Lookup accounts by ID | `AccountBatch` |
| `lookupTransfers(IdBatch $ids): TransferBatch` | Lookup transfers by ID | `TransferBatch` |
| `getAccountTransfers(AccountFilterBatch $filter): TransferBatch` | Get transfers for an account | `TransferBatch` |
| `getAccountBalances(AccountFilterBatch $filter): AccountBalanceBatch` | Get account balances | `AccountBalanceBatch` |
| `queryAccounts(QueryFilter $filter): AccountBatch` | Query accounts by filter | `AccountBatch` |
| `queryTransfers(QueryFilter $filter): TransferBatch` | Query transfers by filter | `TransferBatch` |

### Request Timeout

By default each request waits up to **30 seconds** for the native TigerBeetle client
to complete before throwing a `RequestTimeoutException`.  You can override this on a
per-client basis using the `Client::withTimeout()` factory:

```php
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\Uint128\Uint128;

// 5-second timeout
$client = Client::withTimeout(
    Uint128::zero(),
    5.0,
    '127.0.0.1:3000',
);

// Default (30 s) timeout
$client = new Client(Uint128::zero(), '127.0.0.1:3000');

// Pass null to use the backend default explicitly
$client = Client::withTimeout(Uint128::zero(), null, '127.0.0.1:3000');
```

When the timeout expires, a `CrazyGoat\Elephas\Exception\RequestTimeoutException`
is thrown — a subclass of `\RuntimeException` that implements the project's
`ElephasExceptionInterface`.  You can catch it to distinguish timeout failures
from other request errors:

```php
use CrazyGoat\Elephas\Exception\RequestTimeoutException;

try {
    $result = $client->createAccounts($accounts);
} catch (RequestTimeoutException $e) {
    // $e->getTimeoutSeconds() returns the configured timeout value
    echo "Timed out after " . $e->getTimeoutSeconds() . " s\n";
}
```

### Create Operation Results

`createAccounts()` and `createTransfers()` each return a **result batch** with one
entry per item in the request, in the same positional order:

> `$result->getResult()` position *i* corresponds to the *i*-th item added to the
> request batch.

Each result carries:
- **`getTimestamp(): int`** — the TigerBeetle-assigned timestamp (nanoseconds since
  the TigerBeetle epoch). Only meaningful when the operation succeeded.
- **`getStatus(): CreateAccountStatus|CreateTransferStatus`** — the outcome of the
  operation. `CREATED` (value `0xFFFFFFFF`) means success; any other value is a
  specific error code.
- **`isCreated(): bool`** — shorthand for `getStatus() === CreateAccountStatus::CREATED`
  (or the transfer equivalent).

#### Success and Partial Failure

A batch of 100 accounts may have 95 created successfully and 5 that fail with
distinct error codes. Each result is independent — one failure does **not** prevent
other items in the same batch from succeeding.

```php
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Uint128\Uint128;

$batch = new AccountBatch(3);
$batch->add();
$batch->setId(Uint128::fromString('1'));
$batch->setLedger(1);
$batch->setCode(1);

$batch->add();
$batch->setId(Uint128::fromString('2'));
$batch->setLedger(1);
$batch->setCode(1);

$batch->add();
$batch->setId(Uint128::fromString('1')); // duplicate ID – will fail

$results = $client->createAccounts($batch);

for ($i = 0; $i < count($results); $i++) {
    $result = $results->getResult();
    if ($result->isCreated()) {
        printf("Account #%d created (timestamp=%d)\n", $i, $result->getTimestamp());
    } else {
        printf("Account #%d failed: %s\n", $i, $result->getStatus()->name);
    }
    $results->next();
}
```

#### Linked Events

TigerBeetle supports **linked events**: when you set `AccountFlags::LINKED` or
`TransferFlags::LINKED` on an event, the following event in the batch executes
only if the linked event succeeds. If the linked event fails, subsequent events
in the chain receive status `LINKED_EVENT_FAILED` and are skipped.

```php
use CrazyGoat\Elephas\AccountFlags;
use CrazyGoat\Elephas\CreateAccountStatus;

$batch = new AccountBatch(3);
$batch->add();
$batch->setId(Uint128::fromString('10'));
$batch->setLedger(1);
$batch->setCode(1);
$batch->setFlags(AccountFlags::LINKED);          // #10 linked → #11 runs only if #10 succeeds

$batch->add();
$batch->setId(Uint128::fromString('11'));
$batch->setLedger(1);
$batch->setCode(1);
$batch->setFlags(AccountFlags::LINKED);          // #11 linked → #12 runs only if #11 succeeds

$batch->add();
$batch->setId(Uint128::fromString('12'));
$batch->setLedger(1);
$batch->setCode(1);
// no LINKED → chain ends here

$results = $client->createAccounts($batch);

for ($i = 0; $i < count($results); $i++) {
    $result = $results->getResult();
    $status = $result->getStatus();

    match ($status) {
        CreateAccountStatus::CREATED => printf("#%d: created (ts=%d)\n", $i, $result->getTimestamp()),
        CreateAccountStatus::LINKED_EVENT_FAILED => printf("#%d: skipped – linked to a failed event\n", $i),
        CreateAccountStatus::LINKED_EVENT_CHAIN_OPEN => printf("#%d: last linked event has no successor\n", $i),
        default => printf("#%d: failed – %s\n", $i, $status->name),
    };

    $results->next();
}
```

> **Important:** If the last event in a batch has `LINKED` set, TigerBeetle
> returns `LINKED_EVENT_CHAIN_OPEN` for that event because the chain is
> unterminated. Always ensure the final linked event is followed by an
> unlinked event (even a dummy one) or does not carry the `LINKED` flag.

#### Result Semantics Summary

| Aspect | Behaviour |
|--------|-----------|
| **Positional correspondence** | Result *i* corresponds to request item *i* |
| **Timestamp** | Valid only when `isCreated()` is true; zero otherwise |
| **Success status** | `CreateAccountStatus::CREATED` / `CreateTransferStatus::CREATED` (value `0xFFFFFFFF`) |
| **Error status** | Any other enum value indicates a specific failure reason |
| **Partial failure** | Some items may succeed while others fail in the same batch |
| **Linked events** | A failed linked event causes subsequent linked events to be skipped |
| **Unterminated chain** | The last linked event must be followed by an unlinked event, or it receives `LINKED_EVENT_CHAIN_OPEN` |

### Uint128

| Factory | Description |
|---------|-------------|
| `Uint128::zero(): self` | Returns zero |
| `Uint128::fromInt(int $value): self` | From signed 64-bit integer |
| `Uint128::fromString(string $decimal): self` | From decimal string (GMP/BCMath accelerated when available) |
| `Uint128::fromParts(int $low, int $high): self` | From low/high 64-bit parts |
| `Uint128::fromBytes(string $bytes): self` | From 16-byte little-endian binary |
| `Uint128::fromHex(string $hex): self` | From hexadecimal string |

| Method | Description |
|--------|-------------|
| `toInt(): int` | Convert to signed 64-bit integer |
| `toFloat(): float` | Convert to float |
| `toString(): string` | Convert to decimal string (GMP/BCMath accelerated when available) |
| `toHex(): string` | Convert to hex string (lowercase, no prefix) |
| `toBytes(): string` | Convert to 16-byte little-endian binary |
| `toArray(): array{low: int, high: int}` | Convert to low/high parts |
| `equals(self $other): bool` | Equality check |
| `compareTo(self $other): int` | Comparison (-1, 0, 1) |
| `isZero(): bool` | Check if zero |

`Uint128::fromString()` and `Uint128::toString()` automatically use the fastest
available implementation:
1. **GMP** – fastest, native C 128-bit arithmetic via `ext-gmp`
2. **BCMath** – secondary acceleration via `ext-bcmath`
3. **Pure PHP** – byte-level arithmetic using only core PHP

No configuration is needed; the class detects available extensions at runtime
and transparently selects the best path. Results are identical regardless of
which path is used.

### Id (ULID)

| Method | Description |
|--------|-------------|
| `Id::generate(): Uint128` | Generate a monotonic ULID |
| `Id::toString(Uint128 $id): string` | Encode ULID to Crockford Base32 |
| `Id::fromString(string $ulid): Uint128` | Parse Crockford Base32 to Uint128 |
| `Id::extractTimestamp(Uint128 $id): int` | Extract millisecond timestamp |
| `Id::extractRandom(Uint128 $id): string` | Extract random bytes |

### Lookup behaviour

`lookupAccounts()` and `lookupTransfers()` always return exactly one result per requested ID, in the same order.

When a requested record **does not exist**, TigerBeetle returns a **zeroed struct** (all fields set to zero). Use `isFound()` to check whether the current record was found:

```php
$ids = new IdBatch(2);
$ids->add();
$ids->setId(Uint128::fromString('100'));
$ids->add();
$ids->setId(Uint128::fromString('999'));

$accounts = $client->lookupAccounts($ids);

$accounts->rewind();
var_dump($accounts->isFound()); // true  – account 100 exists

$accounts->next();
var_dump($accounts->isFound()); // false – account 999 does not exist
```

A found record always has a non-zero ID and a non-zero timestamp (`getTimestamp() > 0`).

### Querying accounts and transfers

`queryAccounts()` and `queryTransfers()` stream records that match a `QueryFilter` across the
cluster. A `QueryFilter` field set to `0` (or `Uint128::zero()` for `user_data_128`) acts as a
wildcard; non-zero values are exact-match predicates. Combine `REVERSED` with `QueryFilterFlags::REVERSED`
to iterate events in newest-first order, and use `limit` to cap the number of returned records.

```php
use CrazyGoat\Elephas\QueryFilter;
use CrazyGoat\Elephas\QueryFilterFlags;
use CrazyGoat\Elephas\Uint128\Uint128;

// Accounts with a specific user_data_128, oldest first, capped at 100 results.
$filter = new QueryFilter(
    userData128: Uint128::fromInt(0xABCDEF),
    limit: 100,
    flags: 0,
);

$accounts = $client->queryAccounts($filter);
$accounts->rewind();
while ($accounts->valid()) {
    $id = $accounts->getId();
    $ledger = $accounts->getLedger();
    // ...
    $accounts->next();
}

// Transfers, newest first.
$reversed = $client->queryTransfers(
    new QueryFilter(flags: QueryFilterFlags::REVERSED),
);
```

### Integer field ranges

The integer setters on mutable batch classes validate that values fit their declared unsigned width
before binary packing. A value that is out of range raises `IntegerOverflowException` with the
offending field name and the accepted `[min, max]` range.

| Field width | Setter examples | Accepted range |
|-------------|-----------------|----------------|
| `uint16`    | `setCode`, `setFlags` (Account/Transfer) | `[0, 65535]` |
| `uint32`    | `setUserData32`, `setLedger`, `setTimeout`, `setLimit`, `setFlags` (filter batches) | `[0, 4294967295]` |
| `uint64`    | `setUserData64`, `setTimestampMin`, `setTimestampMax` | `[0, PHP_INT_MAX]` |

Values that exceed `PHP_INT_MAX` cannot be represented as a PHP signed `int` and must be modelled
with `Uint128` instead. Negative values that would otherwise be silently reinterpreted as huge
unsigned values by `pack('P', …)` are rejected up front.

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
| `ChangeEventsFilterBatch` | 16 bytes | Yes | Change events filter by account ID |

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

## FFI Security

Elephas uses PHP's [FFI](https://www.php.net/manual/en/book.ffi.php) (Foreign Function Interface) to load and execute
the native `tb_client` shared library. Because FFI runs native code directly inside the PHP process, the native library
**must** come from a trusted source.

### Trust model

- The `tb_client` library (and the companion `libelephas_noop.so`) is loaded into the PHP process address space.
  A compromised or malicious library can execute arbitrary code, read process memory, and access all data the PHP
  process has access to.
- Only load libraries downloaded from the official
  [GitHub Releases](https://github.com/crazy-goat/elephas/releases) or built from the trusted source repository
  ([crazy-goat/elephas](https://github.com/crazy-goat/elephas)).
- In production, **always specify an explicit, trusted library path** using the `$libPath` parameter:

  ```php
  use CrazyGoat\Elephas\Backend\BackendFactory;
  use CrazyGoat\Elephas\Client;
  use CrazyGoat\Elephas\Uint128\Uint128;

  $backend = BackendFactory::create(
      clusterId: Uint128::fromInt(0),
      replicaAddresses: ['127.0.0.1:3000'],
      libPath: '/opt/elephas/resources/lib/x86_64-linux-gnu/libtb_client.so',
  );
  $client = Client::withBackend($backend);
  ```

### Loading precedence

When `$libPath` is not specified, only project-local paths under `resources/lib/` are searched:

1. `resources/lib/{platform-dir}/libtb_client.so`
2. `resources/lib/{platform-dir}/libtb_client.dylib`

System-wide paths (`/usr/local/lib`, `/usr/lib`, etc.) are **not** searched automatically. This prevents accidental
loading of an untrusted or version-mismatched library that could be placed in a system directory by another package
or an attacker.

### Best practices

| Practice | Recommendation |
|----------|---------------|
| Library source | Download from official GitHub Releases only |
| Explicit path | Use `$libPath` in `BackendFactory::create()` in production |
| File permissions | Restrict read access to the library file to the PHP process user |
| Integrity | Verify the library's SHA-256 checksum against the published release checksums |
| Companion library | `libelephas_noop.so` (if present) must come from the same trusted source as `tb_client` |

## Architecture

A native shared library (`tb_client`) communicates with TigerBeetle via FFI. The `NativeClient` wraps the C API,
`Packet` handles callback synchronization, and high-level batch classes pack/unpack binary data.

For a detailed architecture overview, see [ARCHITECTURE.md](ARCHITECTURE.md) (in Polish).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

Elephas is open-source software released under the [MIT License](LICENSE).
