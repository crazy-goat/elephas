<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

/**
 * Batch of account filters for query operations.
 *
 * TODO: implement
 */
class AccountFilterBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return \CrazyGoat\Elephas\Internal\BinaryHelper::ACCOUNT_FILTER_SIZE;
    }
}
