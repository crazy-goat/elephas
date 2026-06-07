<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Exception\RequestException;
use CrazyGoat\Elephas\Exception\RequestTimeoutException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\InitStatus;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;

class NativeClient
{
    public const PACKET_PENDING = 0xFFFFFFFF;

    public const DEFAULT_TIMEOUT_SECONDS = 30.0;

    private const HEADER = <<<'CPROG'
typedef unsigned char tb_uint128_t[16];

typedef struct tb_client_t tb_client_t;

typedef enum {
    TB_INIT_SUCCESS = 0,
    TB_INIT_UNEXPECTED = 1,
    TB_INIT_OUT_OF_MEMORY = 2,
    TB_INIT_INVALID_ADDRESS = 3,
    TB_INIT_SYSTEM_RESOURCES = 4,
    TB_INIT_NETWORK_SUBSYSTEM = 5,
} tb_init_status_t;

typedef enum {
    TB_PACKET_OK = 0,
    TB_PACKET_TOO_MUCH_DATA = 1,
    TB_PACKET_INVALID_OPERATION = 2,
    TB_PACKET_INVALID_DATA_SIZE = 3,
    TB_PACKET_ZERO_ADDRESS = 4,
    TB_PACKET_ZERO_CLUSTER_ID = 5,
    TB_PACKET_CONCURRENCY_MAX_EXCEEDED = 6,
} tb_packet_status_t;

typedef struct tb_packet_t {
    uint64_t user_data;
    uint16_t operation;
    uint32_t status;
    uint32_t data_size;
    uint8_t* data;
    struct tb_packet_t* next;
    uintptr_t callback_context;
    void (*callback)(uintptr_t, struct tb_packet_t*, uint64_t, const uint8_t*, uint32_t);
} tb_packet_t;

int tb_client_init(
    tb_client_t** out_client,
    tb_uint128_t cluster_id,
    const char* addresses,
    uint32_t addresses_count,
    uintptr_t callback_context,
    void (*callback)(uintptr_t, tb_packet_t*, uint64_t, const uint8_t*, uint32_t)
);

void tb_client_deinit(tb_client_t* client);

void tb_client_submit(tb_client_t* client, tb_packet_t* packet);

CPROG;

    protected readonly \FFI $ffi;

    protected readonly \FFI\CData $noopCallback;

    protected \FFI\CData $client;

    private bool $initialized = false;

    protected readonly string $libPath;

    protected float $timeoutSeconds;

    /**
     * @param string|null    $libPath         path to the tb_client shared library, or null for auto-detect
     * @param float|null     $timeoutSeconds  request completion timeout in seconds; null falls back to
     *                                        {@see self::DEFAULT_TIMEOUT_SECONDS}. Must be > 0.
     *
     * @throws \InvalidArgumentException if $timeoutSeconds is not positive
     */
    public function __construct(?string $libPath = null, ?float $timeoutSeconds = null)
    {
        $this->timeoutSeconds = $timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS;

        if ($this->timeoutSeconds <= 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Request timeout must be positive, got %.3f s',
                $this->timeoutSeconds,
            ));
        }

        $this->libPath = $libPath ?? $this->detectLibraryPath();

        try {
            $this->ffi = \FFI::cdef(self::HEADER, $this->libPath);
        } catch (\FFI\Exception $e) {
            throw InitializationException::create(
                \sprintf('Cannot load tb_client library from %s: %s', $this->libPath, $e->getMessage()),
            );
        }

        $this->noopCallback = $this->loadNoopCallback($this->libPath);
    }

    /**
     * Load a thread-safe no-op completion callback from a companion shared
     * library.  tb_client calls the completion callback on its I/O thread;
     * a PHP closure cannot be safely invoked from a foreign thread.
     */
    private function loadNoopCallback(string $tbLibPath): \FFI\CData
    {
        $noopDir = \dirname($tbLibPath);
        $noopLib = $noopDir . '/libelephas_noop.so';

        if (\file_exists($noopLib)) {
            $noop = \FFI::cdef(
                'void elephas_noop(unsigned long, void*, unsigned long long, const void*, unsigned int);',
                $noopLib,
            );

            /** @phpstan-ignore property.notFound */
            return $noop->elephas_noop;
        }

        // Fall back to free(NULL) from glibc — compatible ABI on x86_64.
        // free(NULL) is a defined no-op; extra register arguments are ignored.
        $libc = \FFI::cdef(
            'void free(uintptr_t, void*, unsigned long long, const void*, unsigned int);',
            'libc.so.6',
        );

        /** @phpstan-ignore property.notFound */
        return $libc->free;
    }

    /** @param array<string> $addresses */
    public function init(string $clusterId, array $addresses): void
    {
        if (\strlen($clusterId) !== 16) {
            throw new \ValueError(\sprintf(
                'Cluster ID must be exactly 16 bytes, got %d',
                \strlen($clusterId),
            ));
        }

        /** @phpstan-var \FFI\CData $cClusterId */
        $cClusterId = $this->ffi->new('tb_uint128_t');
        \FFI::memcpy($cClusterId, $clusterId, 16);

        $addressString = \implode("\0", $addresses) . "\0";
        /** @phpstan-var \FFI\CData $cAddresses */
        $cAddresses = $this->ffi->new('char[' . \strlen($addressString) . ']');
        \FFI::memcpy($cAddresses, $addressString, \strlen($addressString));

        /** @phpstan-var \FFI\CData $clientPtr */
        $clientPtr = $this->ffi->new('tb_client_t*');

        $status = $this->callTbClientInit(
            $clientPtr,
            $cClusterId,
            $cAddresses,
            \count($addresses),
            $this->noopCallback,
        );

        if ($status !== 0) {
            try {
                throw InitializationException::fromStatus(InitStatus::from($status));
            } catch (\ValueError) {
                throw InitializationException::create(
                    \sprintf('Unknown initialization status %d from native library', $status),
                );
            }
        }

        $this->client = $clientPtr;
        $this->initialized = true;
    }

    public function submit(Operation $operation, string $data): string
    {
        /** @phpstan-var \FFI\CData $cPacket */
        $cPacket = $this->ffi->new('tb_packet_t');

        // Keep a PHP reference to the underlying data buffer for the entire
        // native request lifetime.  FFI::cast() does not extend the source
        // CData lifetime — without this reference the uint8_t[] memory could
        // be freed while tb_client still holds the raw pointer.
        /** @phpstan-var \FFI\CData $dataBuffer */
        $dataBuffer = $this->createDataBuffer($data);

        /** @phpstan-ignore property.notFound */
        $cPacket->user_data = 0;
        /** @phpstan-ignore property.notFound */
        $cPacket->operation = $operation->value;
        /** @phpstan-ignore property.notFound */
        $cPacket->status = self::PACKET_PENDING;
        /** @phpstan-ignore property.notFound */
        $cPacket->data_size = \strlen($data);
        /** @phpstan-ignore property.notFound */
        $cPacket->data = $this->ffi->cast('uint8_t*', $dataBuffer);
        /** @phpstan-ignore property.notFound */
        $cPacket->callback_context = 0;

        $this->callTbClientSubmit($this->client, $cPacket);

        return $this->pollForCompletion($cPacket);
        // $dataBuffer goes out of scope here, after the response has been
        // read inside pollForCompletion() — no additional long-lived memory.
    }

    public function deinit(): void
    {
        if ($this->initialized) {
            $this->callTbClientDeinit($this->client);
            $this->initialized = false;
        }
    }

    public function getFfi(): \FFI
    {
        return $this->ffi;
    }

    protected function callTbClientInit(
        \FFI\CData $clientPtr,
        \FFI\CData $clusterId,
        \FFI\CData $addresses,
        int $addressCount,
        \FFI\CData $callback,
    ): int {
        /** @phpstan-ignore method.notFound */
        return $this->ffi->tb_client_init(
            \FFI::addr($clientPtr),
            $clusterId,
            $addresses,
            $addressCount,
            0,
            $callback,
        );
    }

    protected function callTbClientSubmit(\FFI\CData $client, \FFI\CData $packet): void
    {
        /** @phpstan-ignore method.notFound */
        $this->ffi->tb_client_submit($client, \FFI::addr($packet));
    }

    protected function callTbClientDeinit(\FFI\CData $client): void
    {
        /** @phpstan-ignore method.notFound */
        $this->ffi->tb_client_deinit($client);
    }

    protected function createDataBuffer(string $data): \FFI\CData
    {
        $size = \strlen($data);

        if ($size === 0) {
            /** @phpstan-var \FFI\CData $buffer */
            $buffer = $this->ffi->new('uint8_t[1]');
            \FFI::memset($buffer, 0, 1);

            return $buffer;
        }

        /** @phpstan-var \FFI\CData $buffer */
        $buffer = $this->ffi->new('uint8_t[' . $size . ']');
        \FFI::memcpy($buffer, $data, $size);

        return $buffer;
    }

    protected function pollForCompletion(\FFI\CData $packet): string
    {
        $deadline = \microtime(true) + $this->timeoutSeconds;

        /** @phpstan-ignore property.notFound */
        while ((int) $packet->status === self::PACKET_PENDING) {
            if (\microtime(true) >= $deadline) {
                throw RequestTimeoutException::create($this->timeoutSeconds);
            }

            usleep(1000);
        }

        /** @phpstan-ignore property.notFound */
        $statusCode = (int) $packet->status;
        /** @phpstan-ignore property.notFound */
        $dataSize = (int) $packet->data_size;

        return $this->processCompletionResult(
            $statusCode,
            $dataSize,
            $dataSize > 0
                /** @phpstan-ignore property.notFound */
                ? \FFI::string($packet->data, $dataSize)
                : '',
        );
    }

    protected function processCompletionResult(int $statusCode, int $dataSize, string $data): string
    {
        if ($statusCode === self::PACKET_PENDING) {
            throw RequestTimeoutException::create($this->timeoutSeconds);
        }

        if ($statusCode !== PacketStatus::OK->value) {
            throw $this->createException($statusCode, $dataSize);
        }

        return $data;
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    private function createException(int $statusCode, int $dataSize): \RuntimeException
    {
        return match ($statusCode) {
            1 => TooMuchDataException::create(1024 * 1024, $dataSize),
            2 => RequestException::create($statusCode, 'Invalid operation'),
            3 => RequestException::create($statusCode, 'Invalid data size'),
            4 => RequestException::create($statusCode, 'Zero address'),
            5 => RequestException::create($statusCode, 'Zero cluster ID'),
            6 => RequestException::create($statusCode, 'Concurrency max exceeded'),
            default => RequestException::create($statusCode),
        };
    }

    private function detectLibraryPath(): string
    {
        $uname = \php_uname('s') . '/' . \php_uname('m');
        $platform = match (true) {
            \str_starts_with($uname, 'Linux/arm'),
            \str_starts_with($uname, 'Linux/aarch64') => 'linux-arm64',
            \str_starts_with($uname, 'Linux/x86_64'),
            \str_starts_with($uname, 'Linux/AMD64') => 'linux-amd64',
            \str_starts_with($uname, 'Darwin/arm') => 'macos-arm64',
            \str_starts_with($uname, 'Darwin/x86_64') => 'macos-amd64',
            default => throw InitializationException::create(
                \sprintf('Unsupported platform: %s', $uname),
            ),
        };

        $paths = [
            \dirname(__DIR__, 2) . "/resources/lib/{$platform}/libtb_client.so",
            \dirname(__DIR__, 2) . "/resources/lib/{$platform}/libtb_client.dylib",
            '/usr/local/lib/libtb_client.so',
            '/usr/lib/libtb_client.so',
        ];

        foreach ($paths as $path) {
            if (\file_exists($path)) {
                return $path;
            }
        }

        throw InitializationException::create(
            \sprintf('Cannot find tb_client library for platform %s', $platform),
        );
    }
}
