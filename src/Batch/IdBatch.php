<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

class IdBatch extends AbstractBatch
{
    private const ID = 0;

    protected function getStructSize(): int
    {
        return BinaryHelper::UINT128_SIZE;
    }

    public function setId(Uint128 $id): void
    {
        $offset = $this->currentPosition * $this->getStructSize() + self::ID;
        $this->buffer = \substr_replace($this->buffer, $id->toBytes(), $offset, 16);
    }

    public function getId(): Uint128
    {
        $offset = $this->currentPosition * $this->getStructSize() + self::ID;

        return Uint128::fromBytes(\substr($this->buffer, $offset, 16));
    }
}
