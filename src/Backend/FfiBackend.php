<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Operation;

/**
 * FFI backend for TigerBeetle communication.
 *
 * Uses PHP FFI to communicate with tb_client native library.
 */
class FfiBackend extends AbstractBackend
{
    /**
     * @param array<string> $replicaAddresses
     *
     * TODO: implement
     */
    public function __construct(private readonly string $clusterId, private readonly array $replicaAddresses)
    {
    }

    public function submit(
        Operation $operation,
        string $data,
    ): string {
        $this->ensureNotClosed();

        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function getClusterId(): string
    {
        return $this->clusterId;
    }

    /** @return array<string> */
    public function getReplicaAddresses(): array
    {
        return $this->replicaAddresses;
    }

    public function close(): void
    {
        // TODO: implement
        parent::close();
    }
}
