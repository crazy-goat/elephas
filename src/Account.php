<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * TigerBeetle account data structure.
 *
 * Represents a financial account with balance tracking.
 * Fields map to the native tb_account_t struct.
 */
final readonly class Account
{
    public function __construct(
        private Uint128 $id,
        private Uint128 $debitsPending,
        private Uint128 $debitsPosted,
        private Uint128 $creditsPending,
        private Uint128 $creditsPosted,
        private Uint128 $userData128,
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

    public function getDebitsPending(): Uint128
    {
        return $this->debitsPending;
    }

    public function getCreditsPending(): Uint128
    {
        return $this->creditsPending;
    }

    public function getDebitsPosted(): Uint128
    {
        return $this->debitsPosted;
    }

    public function getCreditsPosted(): Uint128
    {
        return $this->creditsPosted;
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
