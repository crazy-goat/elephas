<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

/**
 * FFI binding to tb_client native library.
 *
 * Provides low-level access to the TigerBeetle C client.
 */
class NativeClient
{
    private readonly \FFI $ffi;

    /**
     * Initialize the native client.
     *
     * @throws \RuntimeException if FFI extension is not available
     * @throws \RuntimeException if tb_client library cannot be loaded
     *
     * TODO: implement
     */
    public function __construct()
    {
        $this->ffi = \FFI::cdef('');
        // TODO: implement
    }

    /**
     * Submit a packet to TigerBeetle.
     *
     * @param int $operation Operation code
     * @param string $data Binary data
     *
     * @return string Response data
     *
     * TODO: implement
     */
    public function submit(int $operation, string $data): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function getFfi(): \FFI
    {
        return $this->ffi;
    }

    /**
     * Close the native client.
     *
     * TODO: implement
     */
    public function close(): void
    {
        // TODO: implement
    }
}
