<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Account filter for querying transfers and balances.
 *
 * Used with getAccountTransfers() and getAccountBalances() methods.
 */
final readonly class AccountFilter
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $accountId,
        private int $UserData128 = 0,
        private int $UserData64 = 0,
        private int $UserData32 = 0,
        private int $timestampMin = 0,
        private int $timestampMax = 0,
        private int $limit = 0,
        private int $flags = 0,
    ) {
    }

    public function getAccountId(): Uint128
    {
        return $this->accountId;
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
