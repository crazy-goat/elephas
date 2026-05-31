<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Batch of transfers for createTransfers() operation.
 *
 * Provides setters and getters for transfer fields.
 */
class TransferBatch extends AbstractBatch
{
    /**
     * TODO: implement
     */
    public function add(): void
    {
        // TODO: implement
    }

    public function setId(Uint128 $id): void
    {
        // TODO: implement
    }

    public function getId(): Uint128
    {
        // TODO: implement
        return Uint128::zero();
    }

    public function setDebitAccountId(Uint128 $id): void
    {
        // TODO: implement
    }

    public function getDebitAccountId(): Uint128
    {
        // TODO: implement
        return Uint128::zero();
    }

    public function setCreditAccountId(Uint128 $id): void
    {
        // TODO: implement
    }

    public function getCreditAccountId(): Uint128
    {
        // TODO: implement
        return Uint128::zero();
    }

    public function setAmount(int $amount): void
    {
        // TODO: implement
    }

    public function getAmount(): int
    {
        // TODO: implement
        return 0;
    }

    public function setPendingId(Uint128 $id): void
    {
        // TODO: implement
    }

    public function setUserData128(Uint128 $value): void
    {
        // TODO: implement
    }

    public function setUserData64(int $value): void
    {
        // TODO: implement
    }

    public function setUserData32(int $value): void
    {
        // TODO: implement
    }

    public function setTimeout(int $value): void
    {
        // TODO: implement
    }

    public function setLedger(int $value): void
    {
        // TODO: implement
    }

    public function setCode(int $value): void
    {
        // TODO: implement
    }

    public function setFlags(int $value): void
    {
        // TODO: implement
    }
}
