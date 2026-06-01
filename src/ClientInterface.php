<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Batch\AccountBalanceBatch;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\AccountFilterBatch;
use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Exception\ClientClosedException;

/**
 * Client interface for TigerBeetle database operations.
 *
 * Defines the contract for all client implementations.
 */
interface ClientInterface
{
    /**
     * Create one or more accounts.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function createAccounts(AccountBatch $batch): CreateAccountResultBatch;

    /**
     * Create one or more transfers.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function createTransfers(TransferBatch $batch): CreateTransferResultBatch;

    /**
     * Lookup accounts by their IDs.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function lookupAccounts(IdBatch $ids): AccountBatch;

    /**
     * Lookup transfers by their IDs.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function lookupTransfers(IdBatch $ids): TransferBatch;

    /**
     * Get transfers for an account.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function getAccountTransfers(AccountFilterBatch $filter): TransferBatch;

    /**
     * Get balances for an account.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function getAccountBalances(AccountFilter $filter): AccountBalanceBatch;

    /**
     * Query accounts with filters.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function queryAccounts(QueryFilter $filter): AccountBatch;

    /**
     * Query transfers with filters.
     *
     * @throws ClientClosedException if the client has been closed
     */
    public function queryTransfers(QueryFilter $filter): TransferBatch;

    /**
     * Close the client and release resources.
     */
    public function close(): void;
}
