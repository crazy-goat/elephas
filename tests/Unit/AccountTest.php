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
        $userData128 = Uint128::fromParts(3, 4);

        $account = new Account(
            id: $id,
            userData128: $userData128,
            debitPending: 100,
            creditPending: 200,
            debitPosted: 300,
            creditPosted: 400,
            debitsReserved: 10,
            creditsReserved: 20,
            debitsAccepted: 30,
            creditsAccepted: 40,
            userData64: 500,
            userData32: 600,
            ledger: 700,
            code: 800,
            flags: 900,
            timestamp: 1000,
        );

        $this->assertSame($id, $account->getId());
        $this->assertSame($userData128, $account->getUserData128());
        $this->assertSame(100, $account->getDebitPending());
        $this->assertSame(200, $account->getCreditPending());
        $this->assertSame(300, $account->getDebitPosted());
        $this->assertSame(400, $account->getCreditPosted());
        $this->assertSame(10, $account->getDebitsReserved());
        $this->assertSame(20, $account->getCreditsReserved());
        $this->assertSame(30, $account->getDebitsAccepted());
        $this->assertSame(40, $account->getCreditsAccepted());
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

        $account = new Account($id, $userData128);

        $this->assertSame(0, $account->getDebitPending());
        $this->assertSame(0, $account->getCreditPending());
        $this->assertSame(0, $account->getDebitPosted());
        $this->assertSame(0, $account->getCreditPosted());
        $this->assertSame(0, $account->getDebitsReserved());
        $this->assertSame(0, $account->getCreditsReserved());
        $this->assertSame(0, $account->getDebitsAccepted());
        $this->assertSame(0, $account->getCreditsAccepted());
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
        $userData128 = Uint128::zero();
        $account = new Account($id, $userData128);

        $refl = new \ReflectionClass($account);
        $this->assertTrue($refl->isReadOnly());
    }
}
