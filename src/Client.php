<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Batch\AccountBalanceBatch;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Main client for TigerBeetle database operations.
 *
 * Provides synchronous API for creating accounts, transfers,
 * and querying data from TigerBeetle cluster.
 */
final class Client implements ClientInterface
{
    private readonly BackendInterface $backend;

    private bool $closed = false;

    /** @var array<string> */
    private readonly array $replicaAddresses;

    /**
     * @param Uint128 $clusterId the cluster ID
     * @param string ...$replicaAddresses the replica addresses
     */
    public function __construct(
        private readonly Uint128 $clusterId,
        string ...$replicaAddresses,
    ) {
        $this->replicaAddresses = $replicaAddresses;
        $this->backend = new FfiBackend($clusterId, $replicaAddresses);
    }

    /**
     * Create a Client instance with a custom backend (for testing/dependency injection).
     *
     * Bypasses the public constructor via reflection so that the default FfiBackend
     * is not instantiated. PHP 8.2+ allows ReflectionProperty::setValue() to set
     * readonly properties from outside the declaring class scope.
     */
    public static function withBackend(BackendInterface $backend): self
    {
        $reflection = new \ReflectionClass(self::class);
        /** @var self $client */
        $client = $reflection->newInstanceWithoutConstructor();

        $clusterIdProperty = $reflection->getProperty('clusterId');
        $replicaAddressesProperty = $reflection->getProperty('replicaAddresses');
        $backendProperty = $reflection->getProperty('backend');

        $clusterIdProperty->setValue($client, Uint128::zero());
        $replicaAddressesProperty->setValue($client, []);
        $backendProperty->setValue($client, $backend);

        return $client;
    }

    public function createAccounts(AccountBatch $batch): CreateAccountResultBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #42
        throw new \RuntimeException('Not implemented');
    }

    public function createTransfers(TransferBatch $batch): CreateTransferResultBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #43
        throw new \RuntimeException('Not implemented');
    }

    public function lookupAccounts(IdBatch $ids): AccountBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #44
        throw new \RuntimeException('Not implemented');
    }

    public function lookupTransfers(IdBatch $ids): TransferBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #45
        throw new \RuntimeException('Not implemented');
    }

    public function getAccountTransfers(AccountFilter $filter): TransferBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #46
        throw new \RuntimeException('Not implemented');
    }

    public function getAccountBalances(AccountFilter $filter): AccountBalanceBatch
    {
        $this->ensureNotClosed();

        // TODO: implement in #47
        throw new \RuntimeException('Not implemented');
    }

    public function queryAccounts(QueryFilter $filter): AccountBatch
    {
        $this->ensureNotClosed();

        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function queryTransfers(QueryFilter $filter): TransferBatch
    {
        $this->ensureNotClosed();

        // TODO: implement
        throw new \RuntimeException('Not implemented');
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

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->backend->close();
        $this->closed = true;
    }

    private function ensureNotClosed(): void
    {
        if ($this->closed) {
            throw ClientClosedException::create();
        }
    }
}
