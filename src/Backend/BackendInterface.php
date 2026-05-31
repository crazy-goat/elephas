<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Operation;

/**
 * Backend interface for TigerBeetle communication.
 *
 * Defines the contract for all backend implementations.
 */
interface BackendInterface
{
    /**
     * Submit a request to TigerBeetle.
     *
     * @param Operation $operation The operation to perform
     * @param string $data Binary data to send
     *
     * @return string Binary response data
     */
    public function submit(
        Operation $operation,
        string $data,
    ): string;

    /**
     * Close the backend and release resources.
     */
    public function close(): void;
}
