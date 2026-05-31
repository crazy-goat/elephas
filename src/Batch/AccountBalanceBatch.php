<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\AccountBalance;

/**
 * Read-only batch of account balances.
 *
 * Contains the results of a getAccountBalances() operation.
 */
class AccountBalanceBatch extends AbstractBatch
{
    /**
     * TODO: implement
     */
    public function add(): void
    {
        throw new \RuntimeException('AccountBalanceBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getBalance(): AccountBalance
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
