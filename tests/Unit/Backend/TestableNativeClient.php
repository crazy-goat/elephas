<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\NativeClient;
use CrazyGoat\Elephas\PacketStatus;

final class TestableNativeClient extends NativeClient
{
    public ?int $initResult = null;

    public ?\Throwable $initException = null;

    public bool $submitShouldTimeout = false;

    public ?int $submitErrorStatus = null;

    public string $submitResultData = '';

    public ?\Throwable $submitException = null;

    public bool $deinitCalled = false;

    public ?\Throwable $deinitException = null;

    public function __construct()
    {
        $this->libPath = '/dev/null';

        $minimalHeader = <<<'CPROG'
typedef unsigned char tb_uint128_t[16];
typedef struct tb_client_t tb_client_t;
typedef unsigned char uint8_t;
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
CPROG;

        $this->ffi = \FFI::cdef($minimalHeader);
        $this->noopCallback = $this->ffi->new('unsigned char');
    }

    public function setClient(\FFI\CData $client): void
    {
        $this->client = $client;
    }

    public function exposeFfi(): \FFI
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
        if ($this->initException !== null) {
            throw $this->initException;
        }

        return $this->initResult ?? 0;
    }

    protected function callTbClientSubmit(\FFI\CData $client, \FFI\CData $packet): void
    {
        if ($this->submitException !== null) {
            throw $this->submitException;
        }
    }

    protected function callTbClientDeinit(\FFI\CData $client): void
    {
        $this->deinitCalled = true;

        if ($this->deinitException !== null) {
            throw $this->deinitException;
        }
    }

    protected function createDataBuffer(string $data): \FFI\CData
    {
        $size = \strlen($data);

        if ($size === 0) {
            $buffer = $this->ffi->new('uint8_t[1]');
            \FFI::memset($buffer, 0, 1);

            return $buffer;
        }

        $buffer = $this->ffi->new('uint8_t[' . $size . ']');
        \FFI::memcpy($buffer, $data, $size);

        return $buffer;
    }

    protected function pollForCompletion(\FFI\CData $packet): string
    {
        if ($this->submitShouldTimeout) {
            throw new \RuntimeException('TigerBeetle request timed out after 30 s');
        }

        if ($this->submitException !== null) {
            throw $this->submitException;
        }

        if ($this->submitErrorStatus !== null) {
            return $this->processCompletionResult($this->submitErrorStatus, 0, '');
        }

        return $this->processCompletionResult(
            PacketStatus::OK->value,
            \strlen($this->submitResultData),
            $this->submitResultData,
        );
    }
}
