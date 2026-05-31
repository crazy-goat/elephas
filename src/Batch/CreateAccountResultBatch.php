<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateAccountResult;

/**
 * Read-only batch of create account results.
 *
 * Contains the results of a createAccounts() operation.
 */
class CreateAccountResultBatch extends AbstractBatch
{
    /**
     * TODO: implement
     */
    public function add(): void
    {
        throw new \RuntimeException('CreateAccountResultBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getResult(): CreateAccountResult
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
