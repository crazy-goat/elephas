<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

/**
 * Batch of query filters for query operations.
 *
 * TODO: implement
 */
class QueryFilterBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return \CrazyGoat\Elephas\Internal\BinaryHelper::QUERY_FILTER_SIZE;
    }
}
