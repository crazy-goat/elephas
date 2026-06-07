<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * TigerBeetle account data structure.
 *
 * Represents a financial account with balance tracking.
 */
final readonly class Account
{
    public function __construct(
        private Uint128 $id,
        private Uint128 $userData128,
        private int $debitPending = 0,
        private int $creditPending = 0,
        private int $debitPosted = 0,
        private int $creditPosted = 0,
        private int $debitsReserved = 0,
        private int $creditsReserved = 0,
        private int $debitsAccepted = 0,
        private int $creditsAccepted = 0,
        private int $userData64 = 0,
        private int $userData32 = 0,
        private int $ledger = 0,
        private int $code = 0,
        private int $flags = 0,
        private int $timestamp = 0,
    ) {
    }

    public function getId(): Uint128
    {
        return $this->id;
    }

    public function getDebitPending(): int
    {
        return $this->debitPending;
    }

    public function getCreditPending(): int
    {
        return $this->creditPending;
    }

    public function getDebitPosted(): int
    {
        return $this->debitPosted;
    }

    public function getCreditPosted(): int
    {
        return $this->creditPosted;
    }

    public function getDebitsReserved(): int
    {
        return $this->debitsReserved;
    }

    public function getCreditsReserved(): int
    {
        return $this->creditsReserved;
    }

    public function getDebitsAccepted(): int
    {
        return $this->debitsAccepted;
    }

    public function getCreditsAccepted(): int
    {
        return $this->creditsAccepted;
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

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
