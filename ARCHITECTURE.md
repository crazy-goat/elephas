# Elephas – Architektura

> PHP client dla TigerBeetle (v0.17.x)  
> Namespace: `CrazyGoat\Elephas`  
> Wymaga PHP ^8.2

---

## Spis treści

1. [Założenia](#1-założenia)
2. [Struktura repozytorium](#2-struktura-repozytorium)
3. [Uint128 – 128-bitowe liczby](#3-uint128--128-bitowe-liczby)
4. [Id – generator ULID](#4-id--generator-ulid)
5. [Enums](#5-enums)
6. [Batch classes (wzór Java)](#6-batch-classes-wzór-java)
7. [Backend – warstwa transportu](#7-backend--warstwa-transportu)
8. [Client – główne API](#8-client--główne-api)
9. [Exceptions](#9-exceptions)
10. [Pre-built native library](#10-pre-built-native-library)
11. [Konfiguracja narzędzi](#11-konfiguracja-narzędzi)
12. [Docker](#12-docker)
13. [Testy](#13-testy)
14. [CI/CD](#14-cicd)

---

## 1. Założenia

- **PHP 8.2+** z `ext-ffi`, `ext-gmp` (suggest), `ext-bcmath` (suggest)
- **TigerBeetle 0.17.x** – komunikacja przez natywną bibliotekę `tb_client`
- **Wymienny backend**: FFI → Extension → Native PHP (kolejność priorytetu)
- **Batch API** jak Java: mutable, z `add()` i setterami
- **128-bit**: prosty obiekt `Uint128` z `toInt()`, `toFloat()`, `toString()`
- **ULID** dla ID kont/transferów
- **Brak async** – synchroniczne blocking API

---

## 2. Struktura repozytorium

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
│   ├── Client.php                # główne API
│   ├── ClientInterface.php       # kontrakt klienta
│   │
│   ├── Uint128/
│   │   └── Uint128.php           # 128-bit number
│   │
│   ├── Id.php                    # generator ULID
│   │
│   ├── Operation.php             # enum operacji
│   ├── PacketStatus.php          # enum statusów pakietu
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
│   │   ├── AbstractBatch.php     # bazowa klasa batcha
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
│   │   ├── BackendInterface.php  # kontrakt transportu
│   │   ├── AbstractBackend.php   # wspólna logika
│   │   ├── FfiBackend.php        # PHP FFI → tb_client.so
│   │   ├── NativeClient.php      # FFI binding do tb_client
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
│       ├── Packet.php            # wrapper na C packet
│       └── BinaryHelper.php      # funkcje binary pack/unpack
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

## 3. Uint128 – 128-bitowe liczby

**Plik:** `src/Uint128/Uint128.php`

Klasa reprezentująca **unsigned 128-bit integer**. Wewnętrznie przechowuje dwie 64-bitowe części (low/high) jako PHP `int` (signed 64-bit).

```php
class Uint128 {
    // Konstruktor prywatny – factory methods
    private function __construct(
        private readonly int $low,   // LSB (unsigned 64-bit)
        private readonly int $high,  // MSB (unsigned 64-bit)
    );
    
    // === Factory methods ===
    public static function zero(): self;
    public static function fromInt(int $value): self;          // value rzutowane na uint64
    public static function fromString(string $decimal): self;  // parsowanie decimal string
    public static function fromParts(int $low, int $high): self;
    public static function fromBytes(string $bytes): self;     // 16 bajtów LE
    public static function fromHex(string $hex): self;         // hex string
    
    // === Konwersje ===
    public function toInt(): int;     // OverflowException jeśli > PHP_INT_MAX
    public function toFloat(): float; // OverflowException jeśli poza zakresem double
    public function toString(): string; // zawsze działa, decimal string
    public function toHex(): string;
    public function toBytes(): string;  // 16 bajtów little-endian
    public function toArray(): array{int low, int high};
    
    // === Arytmetyka ===
    public function isZero(): bool;
}
```

### Zachowanie konwersji

| Metoda | Zakres | Zachowanie |
|--------|--------|------------|
| `toInt()` | 0 … `PHP_INT_MAX` (0x7FFF…) | Zwraca `int` |
| `toInt()` | > `PHP_INT_MAX` | Rzuca `IntegerOverflowException` |
| `toFloat()` | 0 … ~1e308 | Zwraca `float` (utrata precyzji dla > 2^53) |
| `toFloat()` | > `PHP_FLOAT_MAX` | Rzuca `IntegerOverflowException` |
| `toString()` | 0 … 2^128-1 | Zawsze działa, zwraca decimal string |

### Reprezentacja binarna (little-endian)

```
Bajty:  [0..7]   = low (LSB)
        [8..15]  = high (MSB)
```

Zgodność z `tb_uint128_t` w C (`__uint128_t` → little-endian na x86_64).

---

## 4. Id – generator ULID

**Plik:** `src/Id.php`

ID w TigerBeetle to **ULID** – 48-bit timestamp + 80-bit random.

```
| timestamp (48 bit) | random (80 bit) |
| 48 bit UNIX ms     | crypto random   |
```

Implementacja wzorowana na Java/Golang:
- Ostatni timestamp przechowywany w statycznej zmiennej
- Jeśli current timestamp <= lastTimestamp, inkrementujemy random (u80), nie timestamp
- Przy zmianie timestampu generujemy nowy random
- Safe dla wielowątkowości (static lock/mutex)
- Monotoniczność przy interpretacji jako little-endian

```php
class Id {
    public static function generate(): Uint128;
}
```

---

## 5. Enums

Wszystkie enums mapują się 1:1 z `tb_client.h`.

| Enum PHP | Odpowiednik C | Zakres |
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

**Flags** to `int` z bitwise OR (nie enum, bo mogą być łączone).  
Używamy `class` z const int, np.:

```php
class AccountFlags {
    public const LINKED = 1 << 0;
    public const DEBITS_MUST_NOT_EXCEED_CREDITS = 1 << 1;
    // ...
}
```

**Statusy** to `int` (numeryczne kody błędów z TigerBeetle).

---

## 6. Batch classes (wzór Java)

Batch classes są **mutable**. Działają na surowym buforze binarnym.

### Hierarchia

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
    
    // Nawigacja
    public function add(): void;
    public function next(): bool;
    public function prev(): bool;
    public function rewind(): void;
    
    // Stan
    public function getLength(): int;
    public function getCapacity(): int;
    public function isValidPosition(): bool;
    public function isReadOnly(): bool;
}
```

### Przykład AccountBatch

```php
class AccountBatch extends AbstractBatch {
    public function setId(Uint128 $id): void;
    public function getId(): Uint128;
    public function setDebitsPending(Uint128 $value): void;
    public function setDebitsPosted(Uint128 $value): void;
    // ... wszystkie settery/gettery dla pól Account
}
```

### Rozmiary struktur (128-bit → 16 bajtów)

| Struktura | Rozmiar (bajty) |
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

## 7. Backend – warstwa transportu

### BackendInterface

```php
interface BackendInterface {
    public function submit(
        Operation $operation,
        string $data,         // binarny batch do wysłania
    ): string;                // binarny batch result
    
    public function close(): void;
}
```

### FfiBackend

Implementacja używająca PHP FFI → `tb_client.so`.

**Przepływ:**
1. `tb_client_init()` – tworzy klienta C
2. `tb_client_submit()` – wysyła packet
3. Callback (C → PHP) – odbiera wynik
4. Wątek C blokuje, PHP czeka na Event

**Synchronizacja:**
- Ponieważ `tb_client_submit()` jest async (callback w innym wątku C), używamy:
  - `\Fiber` – do suspend/resume (PHP 8.1+)
  - Lub `\parallel\Sync` – jeśli dostępne
  - Alternatywnie: busy-wait na zmiennej shared memory

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

Kolejność detekcji:
1. `ext-ffi` + `tb_client.so` istnieje → `FfiBackend`
2. `ext-elephas` → `ExtensionBackend` (future)
3. Rzuca wyjątkiem jeśli żaden backend niedostępny

**Native library loading precedence:**

Gdy `$libPath` nie jest określony, `NativeClient::detectLibraryPath()` przeszukuje tylko
ścieżki lokalne projektu w następującej kolejności:

1. `resources/lib/{platform}/libtb_client.so`
2. `resources/lib/{platform}/libtb_client.dylib`

Systemowe ścieżki globalne (`/usr/local/lib`, `/usr/lib`, itp.) **nie są** przeszukiwane
automatycznie — zostałoby to uznane za zagrożenie bezpieczeństwa, ponieważ FFI ładuje
kod natywny bezpośrednio do procesu PHP (patrz sekcja bezpieczeństwa poniżej).

**Bezpieczeństwo FFI (🔒):**

Ponieważ PHP FFI wykonuje kod natywny w procesie PHP, biblioteka `tb_client` (oraz
towarzysząca `libelephas_noop.so`) **musi** pochodzić z zaufanego źródła.
- W środowisku produkcyjnym zawsze używaj **jawnej, zaufanej ścieżki** do biblioteki
  poprzez `$libPath` w `BackendFactory::create()`.
- Pobieraj pre-built biblioteki tylko z oficjalnych
  [release assets](https://github.com/crazy-goat/elephas/releases) projektu.
- Nie ładuj bibliotek z niezaufanych lokalizacji — złośliwa biblioteka może uzyskać
  pełną kontrolę nad procesem PHP.
- `loadNoopCallback()` ładuje `libelephas_noop.so` z tego samego katalogu co
  `tb_client` — obie biblioteki muszą pochodzić z tego samego zaufanego źródła.
  W razie braku pliku `libelephas_noop.so` używane jest bezpieczne fallback
  `free(NULL)` z glibc (no-op przy dodatkowych argumentach rejestrowych na x86_64).

---

## 8. Client – główne API

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

Każda metoda:
1. Pobiera wewnętrzny bufer z batcha (`toBytes()`)
2. Wywołuje `$this->backend->submit(Operation::CREATE_ACCOUNTS, $data)`
3. Parsuje wynik binarny do result batcha
4. Zwraca result batch

---

## 9. Exceptions

```
ElephasExceptionInterface (marker)
├── InitializationException    – błąd tb_client_init
├── ClientClosedException      – client zamknięty
├── ClientEvictedException     – sesja evicted
├── ClientReleaseException     – zła wersja klienta
├── TooMuchDataException       – za dużo danych w batchu
├── IntegerOverflowException   – wartość poza zakresem int/float
└── RequestException           – ogólny błąd requestu
```

---

## 10. Pre-built native library

**Proces:** W CI budujemy `tb_client.so` dla wszystkich platform i dołączamy jako assets do release.

```yaml
# docker/Dockerfile.build
FROM tigerbeetle-build AS builder
# Buduje tb_client.so dla danej platformy
```

**Struktura assets:**
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

## 11. Konfiguracja narzędzi

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
- Coverage driver: `pcov` lub `xdebug`

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
      - "3000:3000"
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

## 13. Testy

### Unit

| Test | Co testuje |
|------|------------|
| `Uint128Test` | Factory methods, konwersje, overflow exceptions |
| `IdTest` | Generacja ULID, monotoniczność, unikalność |
| `AccountBatchTest` | Batch API, add/set/get, binary reprezentacja |
| `TransferBatchTest` | Batch API, add/set/get, binary reprezentacja |
| `BinaryHelperTest` | Pack/unpack zgodność z C struct |

### Functional

| Test | Co testuje |
|------|------------|
| `ClientTest` | Init, createAccounts, lookupAccounts, close |
| `TransferTest` | createTransfers, lookupTransfers, two-phase |

Functional tests wymagają running TigerBeetle (docker-compose).

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
    - Build native libraries dla wszystkich platform
    - Create GitHub Release with assets
    - Publish to Packagist (opcjonalnie)
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
