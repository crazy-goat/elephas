<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateTransferResult;

/**
 * Read-only batch of create transfer results.
 *
 * Contains the results of a createTransfers() operation.
 */
class CreateTransferResultBatch extends AbstractBatch
{
    /**
     * TODO: implement
     */
    public function add(): void
    {
        throw new \RuntimeException('CreateTransferResultBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getResult(): CreateTransferResult
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
