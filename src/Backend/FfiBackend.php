<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\Uint128\Uint128;

class FfiBackend extends AbstractBackend
{
    private readonly NativeClient $client;

    /**
     * @param array<string> $replicaAddresses
     */
    public function __construct(
        private readonly Uint128 $clusterId,
        private readonly array $replicaAddresses,
        ?NativeClient $nativeClient = null,
        ?string $libPath = null,
    ) {
        $this->client = $nativeClient ?? new NativeClient($libPath);
        $this->client->init($this->clusterId->toBytes(), $this->replicaAddresses);
    }

    public function submit(
        Operation $operation,
        string $data,
    ): string {
        $this->ensureNotClosed();

        return $this->client->submit($operation, $data);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->client->deinit();
        parent::close();
    }

    public function getClusterId(): Uint128
    {
        return $this->clusterId;
    }

    /** @return array<string> */
    public function getReplicaAddresses(): array
    {
        return $this->replicaAddresses;
    }
}
