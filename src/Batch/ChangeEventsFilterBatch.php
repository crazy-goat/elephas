<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Batch of change events filter identifiers.
 *
 * Each entry is a 128-bit account ID used to filter change events
 * for a specific account.
 *
 * This batch is a placeholder for future change-event operations
 * on the TigerBeetle client.
 */
class ChangeEventsFilterBatch extends AbstractBatch
{
    private const ACCOUNT_ID = 0;

    protected function getStructSize(): int
    {
        return BinaryHelper::UINT128_SIZE;
    }

    public function setAccountId(Uint128 $id): void
    {
        $this->writeUint128(self::ACCOUNT_ID, $id);
    }

    public function getAccountId(): Uint128
    {
        return $this->readUint128(self::ACCOUNT_ID);
    }
}
