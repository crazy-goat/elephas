<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * TigerBeetle transfer data structure.
 *
 * Represents a financial transfer between two accounts.
 */
final readonly class Transfer
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $id,
        private Uint128 $debitAccountId,
        private Uint128 $creditAccountId,
        private int $amount = 0,
        private int $pendingId = 0,
        private int $UserData128 = 0,
        private int $UserData64 = 0,
        private int $UserData32 = 0,
        private int $timeout = 0,
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

    public function getDebitAccountId(): Uint128
    {
        return $this->debitAccountId;
    }

    public function getCreditAccountId(): Uint128
    {
        return $this->creditAccountId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPendingId(): int
    {
        return $this->pendingId;
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

    public function getTimeout(): int
    {
        return $this->timeout;
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
