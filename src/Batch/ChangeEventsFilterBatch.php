<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

/**
 * Batch of change events filters.
 *
 * TODO: implement
 */
class ChangeEventsFilterBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return \CrazyGoat\Elephas\Internal\BinaryHelper::UINT128_SIZE;
    }
}
