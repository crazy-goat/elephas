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
    /**
     * TODO: implement
     */
    public function add(): void
    {
        // TODO: implement
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
