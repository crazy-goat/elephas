<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

final readonly class QueryFilter
{
    public function __construct(
        private Uint128 $userData128,
        private int $userData64 = 0,
        private int $userData32 = 0,
        private int $ledger = 0,
        private int $code = 0,
        private int $timestampMin = 0,
        private int $timestampMax = 0,
        private int $limit = 0,
        private int $flags = 0,
    ) {
    }

    public function getUserData128(): Uint128
    {
        return $this->userData128;
    }

    public function getUserData64(): int
    {
        return $this->userData64;
    }

    public function getUserData32(): int
    {
        return $this->userData32;
    }

    public function getLedger(): int
    {
        return $this->ledger;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getTimestampMin(): int
    {
        return $this->timestampMin;
    }

    public function getTimestampMax(): int
    {
        return $this->timestampMax;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }
}
