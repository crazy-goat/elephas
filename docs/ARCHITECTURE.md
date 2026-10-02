# Elephas – Architecture

> PHP client for TigerBeetle (v0.17.x)  
> Namespace: `CrazyGoat\Elephas`  
> Requires PHP ^8.2

---

## Table of contents

1. [Assumptions](#1-assumptions)
2. [Repository structure](#2-repository-structure)
3. [Uint128 – 128-bit numbers](#3-uint128--128-bit-numbers)
4. [Id – generator ULID](#4-id--generator-ulid)
5. [Enums](#5-enums)
6. [Batch classes (Java pattern)](#6-batch-classes-java-pattern)
7. [Backend – transport layer](#7-backend--transport-layer)
8. [Client – main API](#8-client--main-api)
9. [Exceptions](#9-exceptions)
10. [Pre-built native library](#10-pre-built-native-library)
11. [Tooling configuration](#11-tooling-configuration)
12. [Docker](#12-docker)
13. [Tests](#13-tests)
14. [CI/CD](#14-cicd)

---

## 1. Assumptions

- **PHP 8.2+** z `ext-ffi`, `ext-gmp` (suggest), `ext-bcmath` (suggest)
- **TigerBeetle 0.17.x** – communication through the native `tb_client` library
- **Swappable backend**: FFI → Extension → Native PHP (priority order)
- **Batch API** like Java: mutable, with `add()` and setters
- **128-bit**: a simple `Uint128` object with `toInt()`, `toFloat()`, `toString()`
- **ULID** for account and transfer IDs
- **No async** – synchronous, blocking API

---

## 2. Repository structure

```
elephas/
├── composer.json
├── .php-cs-fixer.dist.php
├── phpstan.neon.dist
├── rector.php
├── phpunit.xml.dist
├── .gitignore
│
├── docker/
│   ├── Dockerfile
│   └── docker-compose.yml
│
├── bin/
│   └── install-git-hook.php
│
├── var/                          # cache (gitignored)
│
├── src/
│   ├── Client.php                # main API
│   ├── ClientInterface.php       # client contract
│   │
│   ├── Uint128/
│   │   └── Uint128.php           # 128-bit number
│   │
│   ├── Id.php                    # ULID generator
│   │
│   ├── Operation.php             # operation enum
│   ├── PacketStatus.php          # packet status enum
│   ├── InitStatus.php            # enum init status
│   ├── ClientStatus.php          # enum client status
│   │
│   ├── AccountFlags.php
│   ├── TransferFlags.php
│   ├── AccountFilterFlags.php
│   ├── QueryFilterFlags.php
│   │
│   ├── CreateAccountStatus.php
│   ├── CreateTransferStatus.php
│   │
│   ├── Account.php               # data class
│   ├── Transfer.php              # data class
│   ├── CreateAccountResult.php   # data class
│   ├── CreateTransferResult.php  # data class
│   ├── AccountBalance.php        # data class
│   ├── QueryFilter.php           # data class
│   │
│   ├── Batch/
│   │   ├── AbstractBatch.php     # base batch class
│   │   ├── AccountBatch.php
│   │   ├── TransferBatch.php
│   │   ├── IdBatch.php
│   │   ├── CreateAccountResultBatch.php
│   │   ├── CreateTransferResultBatch.php
│   │   ├── AccountFilterBatch.php
│   │   ├── AccountBalanceBatch.php
│   │   ├── QueryFilterBatch.php
│   │   └── ChangeEventsFilterBatch.php
│   │
│   ├── Backend/
│   │   ├── BackendInterface.php  # transport contract
│   │   ├── AbstractBackend.php   # shared logic
│   │   ├── FfiBackend.php        # PHP FFI → tb_client.so
│   │   ├── NativeClient.php      # FFI binding for tb_client
│   │   └── BackendFactory.php    # auto-detect backend
│   │
│   ├── Exception/
│   │   ├── ElephasExceptionInterface.php
│   │   ├── InitializationException.php
│   │   ├── ClientClosedException.php
│   │   ├── ClientEvictedException.php
│   │   ├── ClientReleaseException.php
│   │   ├── TooMuchDataException.php
│   │   ├── IntegerOverflowException.php
│   │   └── RequestException.php
│   │
│   └── Internal/
│       ├── Packet.php            # wrapper for the C packet
│       └── BinaryHelper.php      # binary pack/unpack helpers
│
├── tests/
│   ├── Unit/
│   │   ├── Uint128/
│   │   │   └── Uint128Test.php
│   │   ├── Batch/
│   │   │   ├── AccountBatchTest.php
│   │   │   └── TransferBatchTest.php
│   │   ├── IdTest.php
│   │   └── BinaryHelperTest.php
│   │
│   └── Functional/
│       ├── ClientTest.php
│       └── TransferTest.php
│
└── .github/
    └── workflows/
        ├── tests.yaml
        └── release.yaml
```

---

## 3. Uint128 – 128-bit numbers

**Plik:** `src/Uint128/Uint128.php`

A class representing an **unsigned 128-bit integer**. Internally it stores two 64-bit parts (low/high) as PHP `int` (signed 64-bit).

```php
class Uint128 {
    // Private constructor – factory methods
    private function __construct(
        private readonly int $low,   // LSB (unsigned 64-bit)
        private readonly int $high,  // MSB (unsigned 64-bit)
    );
    
    // === Factory methods ===
    public static function zero(): self;
    public static function fromInt(int $value): self;          // value cast to uint64
    public static function fromString(string $decimal): self;  // decimal string parsing
    public static function fromParts(int $low, int $high): self;
    public static function fromBytes(string $bytes): self;     // 16 bytes LE
    public static function fromHex(string $hex): self;         // hex string
    
    // === Conversions ===
    public function toInt(): int;     // OverflowException if > PHP_INT_MAX
    public function toFloat(): float; // OverflowException if outside the double range
    public function toString(): string; // always works, decimal string
    public function toHex(): string;
    public function toBytes(): string;  // 16 bytes little-endian
    public function toArray(): array{int low, int high};
    
    // === Arithmetic ===
    public function isZero(): bool;
}
```

### Conversion behavior

| Method | Range | Behavior |
|--------|--------|------------|
| `toInt()` | 0 … `PHP_INT_MAX` (0x7FFF…) | Returns `int` |
| `toInt()` | > `PHP_INT_MAX` | Throws `IntegerOverflowException` |
| `toFloat()` | 0 … ~1e308 | Returns `float` (precision loss for > 2^53) |
| `toFloat()` | > `PHP_FLOAT_MAX` | Throws `IntegerOverflowException` |
| `toString()` | 0 … 2^128-1 | Always works, returns a decimal string |

### Binary representation (little-endian)

```
Bytes:  [0..7]   = low (LSB)
        [8..15]  = high (MSB)
```

Compatible with `tb_uint128_t` in C (`__uint128_t` → little-endian on x86_64).

---

## 4. Id – generator ULID

**Plik:** `src/Id.php`

ID w TigerBeetle to **ULID** – 48-bit timestamp + 80-bit random.

```
| timestamp (48 bit) | random (80 bit) |
| 48 bit UNIX ms     | crypto random   |
```

The implementation follows the Java/Go clients:
- The last timestamp is kept in a static variable
- If the current timestamp <= lastTimestamp, we increment the random part (u80), not the timestamp
- When the timestamp changes, we generate a new random part
- Safe for multithreading (static lock/mutex)
- Monotonic when interpreted as little-endian

```php
class Id {
    public static function generate(): Uint128;
}
```

---

## 5. Enums

All enums map 1:1 to `tb_client.h`.

| PHP enum | C equivalent | Range |
|----------|---------------|--------|
| `Operation` | `TB_OPERATION` | `PULSE=128`, `CREATE_ACCOUNTS=146`, … |
| `PacketStatus` | `TB_PACKET_STATUS` | `OK=0`, `TOO_MUCH_DATA=1`, … |
| `InitStatus` | `TB_INIT_STATUS` | `SUCCESS=0`, `UNEXPECTED=1`, … |
| `ClientStatus` | `TB_CLIENT_STATUS` | `OK=0`, `INVALID=1` |
| `AccountFlags` | `TB_ACCOUNT_FLAGS` | `LINKED`, `DEBITS_MUST_NOT_EXCEED_CREDITS`, … |
| `TransferFlags` | `TB_TRANSFER_FLAGS` | `LINKED`, `PENDING`, `POST_PENDING`, … |
| `AccountFilterFlags` | `TB_ACCOUNT_FILTER_FLAGS` | `DEBITS`, `CREDITS`, `REVERSED` |
| `QueryFilterFlags` | `TB_QUERY_FILTER_FLAGS` | `REVERSED` |
| `CreateAccountStatus` | `TB_CREATE_ACCOUNT_STATUS` | `CREATED=0xFFFFFFFF`, … |
| `CreateTransferStatus` | `TB_CREATE_TRANSFER_STATUS` | `CREATED=0xFFFFFFFF`, … |

**Flags** are `int` values combined with bitwise OR (not enums, because they can be combined).  
We use a `class` with const ints, e.g.:

```php
class AccountFlags {
    public const LINKED = 1 << 0;
    public const DEBITS_MUST_NOT_EXCEED_CREDITS = 1 << 1;
    // ...
}
```

**Statuses** are `int` (numeric error codes from TigerBeetle).

---

## 6. Batch classes (Java pattern)

Batch classes are **mutable**. They work on a raw binary buffer.

### Hierarchy

```
AbstractBatch (abstract)
├── AccountBatch
├── TransferBatch
├── IdBatch
├── CreateAccountResultBatch  (read-only)
├── CreateTransferResultBatch (read-only)
├── AccountFilterBatch
├── AccountBalanceBatch       (read-only)
├── QueryFilterBatch
└── ChangeEventsFilterBatch
```

### AbstractBatch API

```php
abstract class AbstractBatch implements Countable {
    public function __construct(int $capacity);
    
    // Navigation
    public function add(): void;
    public function next(): bool;
    public function prev(): bool;
    public function rewind(): void;
    
    // State
    public function getLength(): int;
    public function getCapacity(): int;
    public function isValidPosition(): bool;
    public function isReadOnly(): bool;
}
```

### AccountBatch example

```php
class AccountBatch extends AbstractBatch {
    public function setId(Uint128 $id): void;
    public function getId(): Uint128;
    public function setDebitsPending(Uint128 $value): void;
    public function setDebitsPosted(Uint128 $value): void;
    // ... all setters/getters for the Account fields
}
```

### Structure sizes (128-bit → 16 bytes)

| Structure | Size (bytes) |
|-----------|----------------|
| `Account` | 128 |
| `Transfer` | 128 |
| `AccountFilter` | 128 |
| `AccountBalance` | 128 |
| `QueryFilter` | 64 |
| `CreateAccountResult` | 16 |
| `CreateTransferResult` | 16 |
| `Uint128` (Id) | 16 |

---

## 7. Backend – transport layer

### BackendInterface

```php
interface BackendInterface {
    public function submit(
        Operation $operation,
        string $data,         // binary batch to send
    ): string;                // binary batch result
    
    public function close(): void;
}
```

### FfiBackend

Implementation using PHP FFI → `tb_client.so`.

**Flow:**
1. `tb_client_init()` – creates the C client
2. `tb_client_submit()` – sends the packet
3. Callback (C → PHP) – receives the result
4. The C thread blocks, PHP waits for an Event

**Synchronization:**
- Because `tb_client_submit()` is async (the callback runs in another C thread), we use:
  - `\Fiber` – for suspend/resume (PHP 8.1+)
  - Or `\parallel\Sync` – if available
  - Alternatively: busy-wait on a shared memory variable

**Pre-built library:**
- `resources/lib/x86_64-linux-gnu/libtb_client.so`
- `resources/lib/aarch64-linux-gnu/libtb_client.so`
- `resources/lib/x86_64-macos/libtb_client.dylib`
- `resources/lib/aarch64-macos/libtb_client.dylib`

### BackendFactory

```php
class BackendFactory {
    public static function create(
        Uint128 $clusterId,
        array $replicaAddresses,
        ?float $timeoutSeconds = null,
        ?string $libPath = null,
    ): BackendInterface;
}
```

Detection order:
1. `ext-ffi` + `tb_client.so` exists → `FfiBackend`
2. `ext-elephas` → `ExtensionBackend` (future)
3. Throws an exception if no backend is available

**Native library loading precedence:**

When `$libPath` is not given, `NativeClient::detectLibraryPath()` searches only the
project-local paths, in this order:

1. `resources/lib/{platform}/libtb_client.so`
2. `resources/lib/{platform}/libtb_client.dylib`

System-wide paths (`/usr/local/lib`, `/usr/lib`, etc.) are **not** searched
automatically. This would be a security risk, because FFI loads native code
directly into the PHP process (see the security notes below).

**FFI security (🔒):**

Because PHP FFI runs native code inside the PHP process, the `tb_client` library (and
the accompanying `libelephas_noop.so`) **must** come from a trusted source.
- In production always use an **explicit, trusted path** to the library
  through `$libPath` in `BackendFactory::create()`.
- Download pre-built libraries only from the project's official
  [release assets](https://github.com/crazy-goat/elephas/releases).
- Do not load libraries from untrusted locations: a malicious library can take
  full control of the PHP process.
- `loadNoopCallback()` loads `libelephas_noop.so` from the same directory as
  `tb_client`, so both libraries must come from the same trusted source.
  If `libelephas_noop.so` is missing, a safe fallback is used: glibc `free(NULL)`
  (a no-op with additional register arguments on x86_64).

---

## 8. Client – main API

```php
class Client {
    public function __construct(
        Uint128 $clusterId,
        string ...$replicaAddresses,
    );
    
    // === Write operations ===
    public function createAccounts(AccountBatch $batch): CreateAccountResultBatch;
    public function createTransfers(TransferBatch $batch): CreateTransferResultBatch;
    
    // === Lookup operations ===
    public function lookupAccounts(IdBatch $ids): AccountBatch;
    public function lookupTransfers(IdBatch $ids): TransferBatch;
    
    // === Query operations ===
    public function getAccountTransfers(AccountFilter $filter): TransferBatch;
    public function getAccountBalances(AccountFilter $filter): AccountBalanceBatch;
    public function queryAccounts(QueryFilter $filter): AccountBatch;
    public function queryTransfers(QueryFilter $filter): TransferBatch;
    
    // === Lifecycle ===
    public function close(): void;
}
```

Every method:
1. Takes the internal buffer from the batch (`toBytes()`)
2. Calls `$this->backend->submit(Operation::CREATE_ACCOUNTS, $data)`
3. Parses the binary result into a result batch
4. Returns the result batch

---

## 9. Exceptions

```
ElephasExceptionInterface (marker)
├── InitializationException    – `tb_client_init` failed
├── ClientClosedException      – client is closed
├── ClientEvictedException     – session evicted
├── ClientReleaseException     – wrong client version
├── TooMuchDataException       – too much data in the batch
├── IntegerOverflowException   – value outside the int/float range
└── RequestException           – generic request error
```

---

## 10. Pre-built native library

**Process:** In CI we build `tb_client.so` for all platforms and attach it as release assets.

```yaml
# docker/Dockerfile.build
FROM tigerbeetle-build AS builder
# Builds tb_client.so for a given platform
```

**Assets layout:**
```
resources/
└── lib/
    ├── x86_64-linux-gnu/
    │   └── libtb_client.so
    ├── aarch64-linux-gnu/
    │   └── libtb_client.so
    ├── x86_64-macos/
    │   └── libtb_client.dylib
    └── aarch64-macos/
        └── libtb_client.dylib
```

---

## 11. Tooling configuration

### composer.json

- `name: crazy-goat/elephas`
- `type: library`
- `require: php ^8.2, ext-ffi`
- `require-dev: php-cs-fixer/shim, phpunit/phpunit ^11.0, phpstan/phpstan, rector/rector`
- `suggest: ext-gmp, ext-bcmath`
- Autoload: PSR-4 `CrazyGoat\Elephas\` → `src/`
- Autoload-dev: PSR-4 `CrazyGoat\Elephas\Test\` → `tests/`

### Code style (.php-cs-fixer.dist.php)

- `@PER-CS2x0` + `@PER-CS2x0:risky`
- `declare_strict_types: true`
- `ordered_imports: true`
- `no_superfluous_phpdoc_tags: true`
- `trailing_comma_in_multiline: [arrays, match, arguments, parameters]`

### Static analysis (phpstan.neon.dist)

- Level 8
- `treatPhpDocTypesAsCertain: false`

### Rector (rector.php)

- PHP 8.2 sets
- `deadCode`, `codeQuality`, `typeDeclarations`

### PHPUnit (phpunit.xml.dist)

- PHPUnit 11.x
- Coverage driver: `pcov` or `xdebug`

---

## 12. Docker

Dev environment with two containers: PHP 8.2 CLI and TigerBeetle 0.17.4.

### docker-compose.yml

```yaml
services:
  tigerbeetle:
    image: ghcr.io/tigerbeetle/tigerbeetle:0.17.4
    entrypoint: >
      sh -c "
        tigerbeetle format --cluster=0 --replica=0 --replica-count=1 --development /data/0_0.tigerbeetle &&
        tigerbeetle start --addresses=3000 --development /data/0_0.tigerbeetle
      "
    ports:
      - "${TIGERBEETLE_PORT:-3000}:3000"
    volumes:
      - tb_data:/data

  elephas:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      TIGERBEETLE_ADDRESS: tigerbeetle:3000
    volumes:
      - ..:/app
    working_dir: /app
    depends_on:
      tigerbeetle:
        condition: service_started
    entrypoint: ["tail", "-f", "/dev/null"]

volumes:
  tb_data:
```

### Dockerfile

- **Base**: `php:8.2-cli-alpine` (smaller image)
- **Extensions**: `ext-ffi`, `ext-gmp`, `ext-bcmath`, `ext-pcntl`, `ext-posix`
- **Composer**: from `composer:latest`
- **TigerBeetle binary**: multi-stage build for `linux/amd64` and `linux/arm64` (for local testing without Docker-in-Docker)

### Usage

```bash
cd docker
docker compose up -d --build
docker compose exec elephas php -v
docker compose exec elephas php -m | grep -E "ffi|gmp|bcmath|pcntl|posix"
docker compose exec elephas composer --version
docker compose down
```

### Validation

Run `docker/validate.sh` to verify all requirements:

```bash
docker/validate.sh
```

---

## 13. Tests

### Unit

| Test | What it tests |
|------|------------|
| `Uint128Test` | Factory methods, conversions, overflow exceptions |
| `IdTest` | ULID generation, monotonicity, uniqueness |
| `AccountBatchTest` | Batch API, add/set/get, binary representation |
| `TransferBatchTest` | Batch API, add/set/get, binary representation |
| `BinaryHelperTest` | Pack/unpack compatibility with the C struct |

### Functional

| Test | What it tests |
|------|------------|
| `ClientTest` | Init, createAccounts, lookupAccounts, close |
| `TransferTest` | createTransfers, lookupTransfers, two-phase |

Functional tests require a running TigerBeetle (docker-compose).

---

## 14. CI/CD

### tests.yaml (PR)

```yaml
jobs:
  lint:
    - composer validate
    - vendor/bin/php-cs-fixer fix --dry-run
    - vendor/bin/phpstan
    - vendor/bin/rector process --dry-run

  tests:
    - matrix: php 8.2, 8.3, 8.4
    - docker-compose up tigerbeetle
    - composer test
```

### release.yaml (tag push)

```yaml
jobs:
  release:
    - Build native libraries for all platforms
    - Create GitHub Release with assets
    - Publish to Packagist (optional)
```

### Container security

The CI workflow runs TigerBeetle inside Docker containers for functional
tests.  Both the `format` and `start` commands currently require
`--privileged` because TigerBeetle uses the `io_uring` system call for
its I/O engine.  On GitHub Actions runners, Docker's default seccomp
and AppArmor profiles block `io_uring` syscalls, and the kernel-level
`kernel.io_uring_disabled` sysctl further restricts access.

Attempts to replace `--privileged` with individual capabilities
(`--cap-add=IPC_LOCK,SYS_RAWIO,SYS_ADMIN` or `--cap-add=ALL`) combined
with `--security-opt seccomp=unconfined --security-opt apparmor=unconfined`
were unsuccessful — only `--privileged` makes `io_uring` available in
this CI environment.

The use of `--privileged` is documented and tracked in issue #130.
If a future TigerBeetle version or a different CI environment removes
the need for it, this should be revisited.
