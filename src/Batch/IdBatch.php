<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Batch of IDs for lookup operations.
 *
 * Used with lookupAccounts() and lookupTransfers() methods.
 */
class IdBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return \CrazyGoat\Elephas\Internal\BinaryHelper::UINT128_SIZE;
    }

    public function setId(Uint128 $id): void
    {
        // TODO: implement
    }

    public function getId(): Uint128
    {
        // TODO: implement
        return Uint128::zero();
    }
}
