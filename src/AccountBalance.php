<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

final readonly class AccountBalance
{
    public function __construct(
        private Uint128 $debitsPending,
        private Uint128 $debitsPosted,
        private Uint128 $creditsPending,
        private Uint128 $creditsPosted,
        private int $timestamp = 0,
    ) {
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

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
