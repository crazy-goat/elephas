<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Batch of accounts for createAccounts() operation.
 *
 * Provides setters and getters for account fields.
 */
class AccountBatch extends AbstractBatch
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

    public function setDebitsPending(Uint128 $value): void
    {
        // TODO: implement
    }

    public function setCreditsPending(Uint128 $value): void
    {
        // TODO: implement
    }

    public function setDebitsPosted(Uint128 $value): void
    {
        // TODO: implement
    }

    public function setCreditsPosted(Uint128 $value): void
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
