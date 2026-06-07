<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\AccountBalanceBatch;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountBalanceBatchTest extends TestCase
{
    public function testConstructor(): void
    {
        $batch = new AccountBalanceBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
    }

    public function testIsReadOnly(): void
    {
        $batch = new AccountBalanceBatch(10);

        $this->assertTrue($batch->isReadOnly());
    }

    public function testAddThrows(): void
    {
        $batch = new AccountBalanceBatch(10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AccountBalanceBatch is read-only');

        $batch->add();
    }

    public function testGetBalanceReadsFromBuffer(): void
    {
        $buffer = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::fromString('1000000000000000000000000000000'),
            'debits_posted' => Uint128::fromString('2000000000000000000000000000000'),
            'credits_pending' => Uint128::fromString('3000000000000000000000000000000'),
            'credits_posted' => Uint128::fromString('4000000000000000000000000000000'),
            'timestamp' => 98765,
        ]);

        $batch = AccountBalanceBatch::fromBuffer($buffer);
        $batch->rewind();
        $balance = $batch->getBalance();

        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($balance->getDebitsPending()),
        );
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000000')->equals($balance->getDebitsPosted()),
        );
        $this->assertTrue(
            Uint128::fromString('3000000000000000000000000000000')->equals($balance->getCreditsPending()),
        );
        $this->assertTrue(
            Uint128::fromString('4000000000000000000000000000000')->equals($balance->getCreditsPosted()),
        );
        $this->assertSame(98765, $balance->getTimestamp());
    }

    public function testMultipleBalances(): void
    {
        $b1 = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::fromString('1000000000000000000000000000000'),
            'debits_posted' => Uint128::zero(),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'timestamp' => 100,
        ]);
        $b2 = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::zero(),
            'debits_posted' => Uint128::fromString('2000000000000000000000000000000'),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'timestamp' => 200,
        ]);

        $batch = AccountBalanceBatch::fromBuffer($b1 . $b2);
        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertSame(100, $batch->getBalance()->getTimestamp());

        $batch->next();
        $this->assertSame(200, $batch->getBalance()->getTimestamp());
    }

    public function testFromBufferSetsCorrectLength(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::ACCOUNT_BALANCE_SIZE * 5);
        $batch = AccountBalanceBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testFromBufferRejectsMalformedBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'AccountBalanceBatch buffer size must be a multiple of 128 bytes, got 100 bytes',
        );

        AccountBalanceBatch::fromBuffer(\str_repeat("\0", 100));
    }

    public function testFromBufferAcceptsEmptyBuffer(): void
    {
        $batch = AccountBalanceBatch::fromBuffer('');

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(0, $batch->getCapacity());
    }

    public function testGetBalanceThrowsOnEmptyBatch(): void
    {
        $batch = AccountBalanceBatch::fromBuffer('');

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . AccountBalanceBatch::class);

        $batch->getBalance();
    }

    public function testMultipleBalancesRoundtrip(): void
    {
        $b1 = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::fromString('1000000000000000000000000000000'),
            'debits_posted' => Uint128::fromString('2000000000000000000000000000000'),
            'credits_pending' => Uint128::fromString('3000000000000000000000000000000'),
            'credits_posted' => Uint128::fromString('4000000000000000000000000000000'),
            'timestamp' => 111,
        ]);
        $b2 = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::fromString('5000000000000000000000000000000'),
            'debits_posted' => Uint128::fromString('6000000000000000000000000000000'),
            'credits_pending' => Uint128::fromString('7000000000000000000000000000000'),
            'credits_posted' => Uint128::fromString('8000000000000000000000000000000'),
            'timestamp' => 222,
        ]);

        $batch = AccountBalanceBatch::fromBuffer($b1 . $b2);
        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $balance1 = $batch->getBalance();
        $this->assertSame(111, $balance1->getTimestamp());

        $batch->next();
        $balance2 = $batch->getBalance();
        $this->assertSame(222, $balance2->getTimestamp());

        // Verify first balance is still accessible after navigation
        $batch->prev();
        $balance1Again = $batch->getBalance();
        $this->assertSame(111, $balance1Again->getTimestamp());
    }

    public function testZeroValues(): void
    {
        $buffer = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::zero(),
            'debits_posted' => Uint128::zero(),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'timestamp' => 0,
        ]);

        $batch = AccountBalanceBatch::fromBuffer($buffer);
        $batch->rewind();
        $balance = $batch->getBalance();

        $this->assertTrue(Uint128::zero()->equals($balance->getDebitsPending()));
        $this->assertTrue(Uint128::zero()->equals($balance->getDebitsPosted()));
        $this->assertTrue(Uint128::zero()->equals($balance->getCreditsPending()));
        $this->assertTrue(Uint128::zero()->equals($balance->getCreditsPosted()));
        $this->assertSame(0, $balance->getTimestamp());
    }

    public function testFromBufferRejectsBufferWithExtraByte(): void
    {
        $validBuffer = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::zero(),
            'debits_posted' => Uint128::zero(),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'timestamp' => 0,
        ]);
        $corruptedBuffer = $validBuffer . "\x01";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 128 bytes');

        AccountBalanceBatch::fromBuffer($corruptedBuffer);
    }

    public function testMaxTimestamp(): void
    {
        $buffer = BinaryHelper::packAccountBalance([
            'debits_pending' => Uint128::zero(),
            'debits_posted' => Uint128::zero(),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'timestamp' => PHP_INT_MAX,
        ]);

        $batch = AccountBalanceBatch::fromBuffer($buffer);
        $batch->rewind();
        $balance = $batch->getBalance();

        $this->assertSame(PHP_INT_MAX, $balance->getTimestamp());
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function invalidBufferSizes(): iterable
    {
        yield '1 byte' => [1];
        yield '127 bytes' => [127];
        yield '129 bytes' => [129];
        yield '255 bytes' => [255];
    }

    #[DataProvider('invalidBufferSizes')]
    public function testFromBufferRejectsInvalidSizes(int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 128 bytes');

        AccountBalanceBatch::fromBuffer(\str_repeat("\0", $size));
    }
}
