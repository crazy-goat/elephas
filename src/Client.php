<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Backend\BackendFactory;
use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Batch\AccountBalanceBatch;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\AccountFilterBatch;
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
        $this->backend = BackendFactory::create($clusterId, $replicaAddresses);
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

        $response = $this->backend->submit(Operation::CREATE_ACCOUNTS, $batch->toBytes());

        return CreateAccountResultBatch::fromBuffer($response);
    }

    public function createTransfers(TransferBatch $batch): CreateTransferResultBatch
    {
        $this->ensureNotClosed();

        $response = $this->backend->submit(Operation::CREATE_TRANSFERS, $batch->toBytes());

        return CreateTransferResultBatch::fromBuffer($response);
    }

    public function lookupAccounts(IdBatch $ids): AccountBatch
    {
        $this->ensureNotClosed();

        $response = $this->backend->submit(Operation::LOOKUP_ACCOUNTS, $ids->toBytes());

        return AccountBatch::fromBuffer($response);
    }

    public function lookupTransfers(IdBatch $ids): TransferBatch
    {
        $this->ensureNotClosed();

        $response = $this->backend->submit(Operation::LOOKUP_TRANSFERS, $ids->toBytes());

        return TransferBatch::fromBuffer($response);
    }

    public function getAccountTransfers(AccountFilterBatch $filter): TransferBatch
    {
        $this->ensureNotClosed();

        $response = $this->backend->submit(Operation::GET_ACCOUNT_TRANSFERS, $filter->toBytes());

        return TransferBatch::fromBuffer($response);
    }

    public function getAccountBalances(AccountFilterBatch $filter): AccountBalanceBatch
    {
        $this->ensureNotClosed();

        $response = $this->backend->submit(Operation::GET_ACCOUNT_BALANCES, $filter->toBytes());

        return AccountBalanceBatch::fromBuffer($response);
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
