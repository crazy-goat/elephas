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
        $this->writeUint128(self::ID, $id);
    }

    public function getId(): Uint128
    {
        return $this->readUint128(self::ID);
    }
}
