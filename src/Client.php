<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Batch\AccountBalanceBatch;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Main client for TigerBeetle database operations.
 *
 * Provides synchronous API for creating accounts, transfers,
 * and querying data from TigerBeetle cluster.
 */
final readonly class Client implements ClientInterface
{
    /** @var array<string> */
    private array $replicaAddresses;

    /**
     * @param Uint128 $clusterId the cluster ID
     * @param string ...$replicaAddresses the replica addresses
     *
     * TODO: implement
     */
    public function __construct(
        private Uint128 $clusterId,
        string ...$replicaAddresses,
    ) {
        $this->replicaAddresses = $replicaAddresses;
    }

    public function createAccounts(AccountBatch $batch): CreateAccountResultBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function createTransfers(TransferBatch $batch): CreateTransferResultBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function lookupAccounts(IdBatch $ids): AccountBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function lookupTransfers(IdBatch $ids): TransferBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function getAccountTransfers(AccountFilter $filter): TransferBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function getAccountBalances(AccountFilter $filter): AccountBalanceBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function queryAccounts(QueryFilter $filter): AccountBatch
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    public function queryTransfers(QueryFilter $filter): TransferBatch
    {
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
        // TODO: implement
    }
}
