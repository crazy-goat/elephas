<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Account balance data structure.
 *
 * Contains debits and credits pending/posted for an account.
 */
final readonly class AccountBalance
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $accountID,
        private Uint128 $UserData128,
        private int $debitsPending = 0,
        private int $creditsPending = 0,
        private int $debitsPosted = 0,
        private int $creditsPosted = 0,
        private int $UserData64 = 0,
        private int $UserData32 = 0,
    ) {
    }

    public function getAccountId(): Uint128
    {
        return $this->accountID;
    }

    public function getDebitsPending(): int
    {
        return $this->debitsPending;
    }

    public function getCreditsPending(): int
    {
        return $this->creditsPending;
    }

    public function getDebitsPosted(): int
    {
        return $this->debitsPosted;
    }

    public function getCreditsPosted(): int
    {
        return $this->creditsPosted;
    }

    public function getUserData128(): Uint128
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
}
