<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

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
        private int $UserData128 = 0,
        private int $UserData64 = 0,
        private int $UserData32 = 0,
        private int $ID = 0,
        private int $IDMax = 0,
        private int $IDMin = 0,
        private int $IDMaxInc = 0,
        private int $limit = 0,
        private int $flags = 0,
    ) {
    }

    public function getUserData128(): int
    {
        return $this->UserData128;
    }

    public function getUserData64(): int
    {
        return $this->UserData64;
    }

    public function getUserData32(): int
    {
        return $this->UserData32;
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
