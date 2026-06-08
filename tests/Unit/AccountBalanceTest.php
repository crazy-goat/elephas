<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\AccountBalance;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class AccountBalanceTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $debitsPending = Uint128::fromParts(1, 2);
        $debitsPosted = Uint128::fromParts(3, 4);
        $creditsPending = Uint128::fromParts(5, 6);
        $creditsPosted = Uint128::fromParts(7, 8);

        $balance = new AccountBalance(
            debitsPending: $debitsPending,
            debitsPosted: $debitsPosted,
            creditsPending: $creditsPending,
            creditsPosted: $creditsPosted,
            timestamp: 98765,
        );

        $this->assertSame($debitsPending, $balance->getDebitsPending());
        $this->assertSame($debitsPosted, $balance->getDebitsPosted());
        $this->assertSame($creditsPending, $balance->getCreditsPending());
        $this->assertSame($creditsPosted, $balance->getCreditsPosted());
        $this->assertSame(98765, $balance->getTimestamp());
    }

    public function testDefaultValuesAreZero(): void
    {
        $zero = Uint128::zero();

        $balance = new AccountBalance(
            debitsPending: $zero,
            debitsPosted: $zero,
            creditsPending: $zero,
            creditsPosted: $zero,
        );

        $this->assertTrue($zero->equals($balance->getDebitsPending()));
        $this->assertTrue($zero->equals($balance->getDebitsPosted()));
        $this->assertTrue($zero->equals($balance->getCreditsPending()));
        $this->assertTrue($zero->equals($balance->getCreditsPosted()));
        $this->assertSame(0, $balance->getTimestamp());
    }

    public function testIsReadonly(): void
    {
        $zero = Uint128::zero();

        $balance = new AccountBalance($zero, $zero, $zero, $zero);

        $refl = new \ReflectionClass($balance);
        $this->assertTrue($refl->isReadOnly());
    }

    public function testZeroValues(): void
    {
        $zero = Uint128::zero();

        $balance = new AccountBalance(
            debitsPending: $zero,
            debitsPosted: $zero,
            creditsPending: $zero,
            creditsPosted: $zero,
            timestamp: 0,
        );

        $this->assertTrue($zero->equals($balance->getDebitsPending()));
        $this->assertTrue($zero->equals($balance->getDebitsPosted()));
        $this->assertTrue($zero->equals($balance->getCreditsPending()));
        $this->assertTrue($zero->equals($balance->getCreditsPosted()));
        $this->assertSame(0, $balance->getTimestamp());
    }

    public function testMaxTimestamp(): void
    {
        $zero = Uint128::zero();

        $balance = new AccountBalance(
            debitsPending: $zero,
            debitsPosted: $zero,
            creditsPending: $zero,
            creditsPosted: $zero,
            timestamp: PHP_INT_MAX,
        );

        $this->assertSame(PHP_INT_MAX, $balance->getTimestamp());
    }

    public function testNullUint128Handling(): void
    {
        $nullLike = Uint128::zero();

        $balance = new AccountBalance(
            debitsPending: $nullLike,
            debitsPosted: $nullLike,
            creditsPending: $nullLike,
            creditsPosted: $nullLike,
        );

        $this->assertTrue($nullLike->equals($balance->getDebitsPending()));
        $this->assertTrue($nullLike->equals($balance->getDebitsPosted()));
        $this->assertTrue($nullLike->equals($balance->getCreditsPending()));
        $this->assertTrue($nullLike->equals($balance->getCreditsPosted()));
    }

    public function testLargeUint128Values(): void
    {
        $maxLow = Uint128::fromParts(-1, 0); // low = 0xFFFFFFFFFFFFFFFF
        $maxHigh = Uint128::fromParts(-1, -1); // max uint128

        $balance = new AccountBalance(
            debitsPending: $maxLow,
            debitsPosted: $maxHigh,
            creditsPending: $maxLow,
            creditsPosted: $maxHigh,
            timestamp: 12345,
        );

        $this->assertTrue($maxLow->equals($balance->getDebitsPending()));
        $this->assertTrue($maxHigh->equals($balance->getDebitsPosted()));
        $this->assertTrue($maxLow->equals($balance->getCreditsPending()));
        $this->assertTrue($maxHigh->equals($balance->getCreditsPosted()));
    }

    public function testTimestampDefault(): void
    {
        $zero = Uint128::zero();

        $balance = new AccountBalance($zero, $zero, $zero, $zero);

        // Default timestamp should be 0
        $this->assertSame(0, $balance->getTimestamp());
    }
}
