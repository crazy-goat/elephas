<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\Transfer;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class TransferTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $id = Uint128::fromParts(1, 2);
        $debitAccountId = Uint128::fromParts(3, 4);
        $creditAccountId = Uint128::fromParts(5, 6);
        $pendingId = Uint128::fromParts(7, 8);
        $userData128 = Uint128::fromParts(9, 10);

        $transfer = new Transfer(
            id: $id,
            debitAccountId: $debitAccountId,
            creditAccountId: $creditAccountId,
            pendingId: $pendingId,
            userData128: $userData128,
            amount: 1000,
            userData64: 500,
            userData32: 600,
            timeout: 30,
            ledger: 700,
            code: 800,
            flags: 900,
            timestamp: 1000,
        );

        $this->assertSame($id, $transfer->getId());
        $this->assertSame($debitAccountId, $transfer->getDebitAccountId());
        $this->assertSame($creditAccountId, $transfer->getCreditAccountId());
        $this->assertSame($pendingId, $transfer->getPendingId());
        $this->assertSame($userData128, $transfer->getUserData128());
        $this->assertSame(1000, $transfer->getAmount());
        $this->assertSame(500, $transfer->getUserData64());
        $this->assertSame(600, $transfer->getUserData32());
        $this->assertSame(30, $transfer->getTimeout());
        $this->assertSame(700, $transfer->getLedger());
        $this->assertSame(800, $transfer->getCode());
        $this->assertSame(900, $transfer->getFlags());
        $this->assertSame(1000, $transfer->getTimestamp());
    }

    public function testDefaultValuesAreZero(): void
    {
        $zero = Uint128::zero();

        $transfer = new Transfer($zero, $zero, $zero, $zero, $zero);

        $this->assertSame(0, $transfer->getAmount());
        $this->assertSame(0, $transfer->getUserData64());
        $this->assertSame(0, $transfer->getUserData32());
        $this->assertSame(0, $transfer->getTimeout());
        $this->assertSame(0, $transfer->getLedger());
        $this->assertSame(0, $transfer->getCode());
        $this->assertSame(0, $transfer->getFlags());
        $this->assertSame(0, $transfer->getTimestamp());
    }

    public function testIsReadonly(): void
    {
        $zero = Uint128::zero();
        $transfer = new Transfer($zero, $zero, $zero, $zero, $zero);

        $refl = new \ReflectionClass($transfer);
        $this->assertTrue($refl->isReadOnly());
    }
}
