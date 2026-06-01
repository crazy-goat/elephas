<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Exception\RequestException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\InitStatus;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;

class NativeClient
{
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
    uint32_t max_concurrency,
    uintptr_t callback_context,
    void (*callback)(uintptr_t, tb_packet_t*, uint64_t, const uint8_t*, uint32_t)
);

void tb_client_deinit(tb_client_t* client);

void tb_client_submit(tb_client_t* client, tb_packet_t* packet);
CPROG;

    private readonly \FFI $ffi;

    private \FFI\CData $client;

    private readonly string $libPath;

    public function __construct(?string $libPath = null)
    {
        $this->libPath = $libPath ?? $this->detectLibraryPath();
        $this->ffi = \FFI::cdef(self::HEADER, $this->libPath);
    }

    /** @param array<string> $addresses */
    public function init(string $clusterId, array $addresses, int $concurrencyMax = 32): void
    {
        \assert(\strlen($clusterId) === 16, 'Cluster ID must be 16 bytes');

        $cClusterId = $this->ffi->new('tb_uint128_t');
        \FFI::memcpy($cClusterId, $clusterId, 16);

        $addressString = \implode("\0", $addresses) . "\0";
        $cAddresses = $this->ffi->new('char[' . \strlen($addressString) . ']');
        \FFI::memcpy($cAddresses, $addressString, \strlen($addressString));

        $clientPtr = $this->ffi->new('tb_client_t*');

        /** @phpstan-ignore method.notFound */
        $status = $this->ffi->tb_client_init(
            \FFI::addr($clientPtr),
            $cClusterId,
            $cAddresses,
            \count($addresses),
            $concurrencyMax,
            0,
            null,
        );

        if ((int) $status !== 0) {
            throw InitializationException::fromStatus(InitStatus::from((int) $status));
        }

        $this->client = $clientPtr[0];
    }

    public function submit(Operation $operation, string $data): string
    {
        /** @phpstan-var \FFI\CData $cPacket */
        $cPacket = $this->ffi->new('tb_packet_t');

        /** @phpstan-ignore property.notFound */
        $cPacket->user_data = 0;
        /** @phpstan-ignore property.notFound */
        $cPacket->operation = $operation->value;
        /** @phpstan-ignore property.notFound */
        $cPacket->status = 0;
        /** @phpstan-ignore property.notFound */
        $cPacket->data_size = \strlen($data);
        /** @phpstan-ignore property.notFound */
        $cPacket->data = $this->ffi->cast('uint8_t*', $this->createDataBuffer($data));
        /** @phpstan-ignore property.notFound */
        $cPacket->callback_context = 0;

        /** @phpstan-ignore method.notFound */
        $this->ffi->tb_client_submit($this->client, \FFI::addr($cPacket));

        return $this->pollForCompletion($cPacket);
    }

    public function deinit(): void
    {
        if (isset($this->client)) {
            /** @phpstan-ignore method.notFound */
            $this->ffi->tb_client_deinit($this->client);
            unset($this->client);
        }
    }

    public function getFfi(): \FFI
    {
        return $this->ffi;
    }

    private function createDataBuffer(string $data): \FFI\CData
    {
        /** @phpstan-var \FFI\CData $buffer */
        $buffer = $this->ffi->new('uint8_t[' . \strlen($data) . ']');
        \FFI::memcpy($buffer, $data, \strlen($data));

        return $buffer;
    }

    private function pollForCompletion(\FFI\CData $packet): string
    {
        $timeout = 30_000_000;
        $elapsed = 0;

        /** @phpstan-ignore property.notFound */
        while ((int) $packet->status === 0) {
            if ($elapsed >= $timeout) {
                throw new \RuntimeException('TigerBeetle request timed out');
            }

            usleep(1000);
            $elapsed += 1000;
        }

        /** @phpstan-ignore property.notFound */
        $statusCode = (int) $packet->status;
        $status = PacketStatus::tryFrom($statusCode);

        if ($status === null || $status !== PacketStatus::OK) {
            /** @phpstan-ignore property.notFound */
            throw $this->createException($statusCode, (int) $packet->data_size);
        }

        /** @phpstan-ignore property.notFound */
        $responseSize = (int) $packet->data_size;

        return $responseSize > 0
            /** @phpstan-ignore property.notFound */
            ? \FFI::string($packet->data, $responseSize)
            : '';
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
