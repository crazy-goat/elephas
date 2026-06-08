<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\Account;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $id = Uint128::fromParts(1, 2);
        $debitsPending = Uint128::fromInt(100);
        $debitsPosted = Uint128::fromInt(300);
        $creditsPending = Uint128::fromInt(200);
        $creditsPosted = Uint128::fromInt(400);
        $userData128 = Uint128::fromParts(3, 4);

        $account = new Account(
            id: $id,
            debitsPending: $debitsPending,
            debitsPosted: $debitsPosted,
            creditsPending: $creditsPending,
            creditsPosted: $creditsPosted,
            userData128: $userData128,
            userData64: 500,
            userData32: 600,
            ledger: 700,
            code: 800,
            flags: 900,
            timestamp: 1000,
        );

        $this->assertSame($id, $account->getId());
        $this->assertSame($userData128, $account->getUserData128());
        $this->assertSame($debitsPending, $account->getDebitsPending());
        $this->assertSame($creditsPending, $account->getCreditsPending());
        $this->assertSame($debitsPosted, $account->getDebitsPosted());
        $this->assertSame($creditsPosted, $account->getCreditsPosted());
        $this->assertSame(500, $account->getUserData64());
        $this->assertSame(600, $account->getUserData32());
        $this->assertSame(700, $account->getLedger());
        $this->assertSame(800, $account->getCode());
        $this->assertSame(900, $account->getFlags());
        $this->assertSame(1000, $account->getTimestamp());
    }

    public function testDefaultValuesAreZero(): void
    {
        $id = Uint128::zero();
        $userData128 = Uint128::zero();
        $zero = Uint128::zero();

        $account = new Account($id, $zero, $zero, $zero, $zero, $userData128);

        $this->assertTrue($account->getDebitsPending()->isZero());
        $this->assertTrue($account->getCreditsPending()->isZero());
        $this->assertTrue($account->getDebitsPosted()->isZero());
        $this->assertTrue($account->getCreditsPosted()->isZero());
        $this->assertSame(0, $account->getUserData64());
        $this->assertSame(0, $account->getUserData32());
        $this->assertSame(0, $account->getLedger());
        $this->assertSame(0, $account->getCode());
        $this->assertSame(0, $account->getFlags());
        $this->assertSame(0, $account->getTimestamp());
    }

    public function testIsReadonly(): void
    {
        $id = Uint128::zero();
        $zero = Uint128::zero();
        $account = new Account($id, $zero, $zero, $zero, $zero, $zero);

        $refl = new \ReflectionClass($account);
        $this->assertTrue($refl->isReadOnly());
    }

    public function testLarge128BitValues(): void
    {
        $large = Uint128::fromString('340282366920938463463374607431768211455');
        $zero = Uint128::zero();

        $account = new Account(
            id: $zero,
            debitsPending: $large,
            debitsPosted: $large,
            creditsPending: $large,
            creditsPosted: $large,
            userData128: $zero,
        );

        $this->assertTrue($account->getDebitsPending()->equals($large));
        $this->assertTrue($account->getDebitsPosted()->equals($large));
        $this->assertTrue($account->getCreditsPending()->equals($large));
        $this->assertTrue($account->getCreditsPosted()->equals($large));
        $this->assertSame('340282366920938463463374607431768211455', $account->getDebitsPending()->toString());
    }
}
