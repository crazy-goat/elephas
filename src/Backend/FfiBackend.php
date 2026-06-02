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
        ?float $timeoutSeconds = null,
    ) {
        $this->client = $nativeClient ?? new NativeClient($libPath, $timeoutSeconds);
        $this->client->init($this->clusterId->toBytes(), $this->replicaAddresses);
    }

    protected function doSubmit(Operation $operation, string $data): string
    {
        return $this->client->submit($operation, $data);
    }

    protected function doClose(): void
    {
        $this->client->deinit();
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
