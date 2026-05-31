<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Query filter for account and transfer queries.
 *
 * Used with queryAccounts() and queryTransfers() methods.
 */
final readonly class QueryFilter
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $userData128,
        private int $userData64 = 0,
        private int $userData32 = 0,
        private int $ID = 0,
        private int $IDMax = 0,
        private int $IDMin = 0,
        private int $IDMaxInc = 0,
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

    public function getID(): int
    {
        return $this->ID;
    }

    public function getIDMax(): int
    {
        return $this->IDMax;
    }

    public function getIDMin(): int
    {
        return $this->IDMin;
    }

    public function getIDMaxInc(): int
    {
        return $this->IDMaxInc;
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
