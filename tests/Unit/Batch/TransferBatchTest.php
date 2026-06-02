<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransferBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new TransferBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new TransferBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetId(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setId($id);

        $this->assertTrue($id->equals($batch->getId()));
    }

    public function testSetAndGetIdAtPosition(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();
        $batch->add();
        $batch->add();

        $id0 = Uint128::fromString('1000000000000000000000000000000');
        $id1 = Uint128::fromString('2000000000000000000000000000000');
        $id2 = Uint128::fromString('3000000000000000000000000000000');

        $batch->rewind();
        $batch->setId($id0);
        $batch->next();
        $batch->setId($id1);
        $batch->next();
        $batch->setId($id2);

        $batch->rewind();
        $this->assertTrue($id0->equals($batch->getId()));
        $batch->next();
        $this->assertTrue($id1->equals($batch->getId()));
        $batch->next();
        $this->assertTrue($id2->equals($batch->getId()));
    }

    public function testSetAndGetDebitAccountId(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setDebitAccountId($id);

        $this->assertTrue($id->equals($batch->getDebitAccountId()));
    }

    public function testSetAndGetCreditAccountId(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $id = Uint128::fromString('2000000000000000000000000000000');
        $batch->setCreditAccountId($id);

        $this->assertTrue($id->equals($batch->getCreditAccountId()));
    }

    public function testSetAndGetAmount(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $amount = Uint128::fromString('3000000000000000000000000000000');
        $batch->setAmount($amount);

        $this->assertTrue($amount->equals($batch->getAmount()));
    }

    public function testSetAndGetPendingId(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $id = Uint128::fromString('4000000000000000000000000000000');
        $batch->setPendingId($id);

        $this->assertTrue($id->equals($batch->getPendingId()));
    }

    public function testSetAndGetUserData128(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $value = Uint128::fromString('5000000000000000000000000000000');
        $batch->setUserData128($value);

        $this->assertTrue($value->equals($batch->getUserData128()));
    }

    public function testSetAndGetUserData64(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setUserData64(0xDEADBEEF);
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
    }

    public function testSetAndGetUserData32(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setUserData32(0xCAFEBABE);
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
    }

    public function testSetAndGetTimeout(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setTimeout(3600);
        $this->assertSame(3600, $batch->getTimeout());
    }

    public function testSetAndGetLedger(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setLedger(777);
        $this->assertSame(777, $batch->getLedger());
    }

    public function testSetAndGetCode(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setCode(100);
        $this->assertSame(100, $batch->getCode());
    }

    public function testSetAndGetFlags(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $batch->setFlags(0b1010);
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testGetTimestampInitiallyZero(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $this->assertSame(0, $batch->getTimestamp());
    }

    public function testMultipleFieldsOnSameItem(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $debitId = Uint128::fromString('2000000000000000000000000000001');
        $creditId = Uint128::fromString('3000000000000000000000000000002');
        $amount = Uint128::fromString('4000000000000000000000000000003');
        $ud128 = Uint128::fromString('5000000000000000000000000000004');

        $batch->setId($id);
        $batch->setDebitAccountId($debitId);
        $batch->setCreditAccountId($creditId);
        $batch->setAmount($amount);
        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setTimeout(3600);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $this->assertTrue($id->equals($batch->getId()));
        $this->assertTrue($debitId->equals($batch->getDebitAccountId()));
        $this->assertTrue($creditId->equals($batch->getCreditAccountId()));
        $this->assertTrue($amount->equals($batch->getAmount()));
        $this->assertTrue($ud128->equals($batch->getUserData128()));
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
        $this->assertSame(3600, $batch->getTimeout());
        $this->assertSame(777, $batch->getLedger());
        $this->assertSame(100, $batch->getCode());
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testIsFoundReturnsTrueForNonZeroId(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));

        $this->assertTrue($batch->isFound());
    }

    public function testIsFoundReturnsFalseForZeroId(): void
    {
        $batch = TransferBatch::fromBuffer(\str_repeat("\0", 128));
        $batch->rewind();

        $this->assertFalse($batch->isFound());
    }

    public function testIsFoundOnEmptyBufferReturnsFalse(): void
    {
        $batch = TransferBatch::fromBuffer('');

        $this->assertFalse($batch->isFound());
    }

    public function testIsFoundMixedBatch(): void
    {
        $source = new TransferBatch(3);
        $source->add();
        $source->setId(Uint128::fromString('1000000000000000000000000000000'));
        $source->add();
        $source->setId(Uint128::fromString('2000000000000000000000000000000'));

        $buffer = $source->getBuffer() . \str_repeat("\0", 128);
        $batch = TransferBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($batch->isFound());
        $batch->next();
        $this->assertTrue($batch->isFound());
        $batch->next();
        $this->assertFalse($batch->isFound());
    }

    public function testFromBufferCreatesCorrectBatch(): void
    {
        $source = new TransferBatch(3);
        $source->add();
        $source->setId(Uint128::fromString('1000000000000000000000000000000'));
        $source->add();
        $source->setId(Uint128::fromString('2000000000000000000000000000000'));

        $buffer = $source->getBuffer();
        $restored = TransferBatch::fromBuffer($buffer);

        $this->assertSame(2, $restored->getLength());

        $restored->rewind();
        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($restored->getId()),
        );
        $restored->next();
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000000')->equals($restored->getId()),
        );
    }

    public function testFromBufferSetsCorrectLength(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::TRANSFER_SIZE * 5);
        $batch = TransferBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testFromBufferRejectsMalformedBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'TransferBatch buffer size must be a multiple of 128 bytes, got 100 bytes',
        );

        TransferBatch::fromBuffer(\str_repeat("\0", 100));
    }

    public function testFromBufferAcceptsEmptyBuffer(): void
    {
        $batch = TransferBatch::fromBuffer('');

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(0, $batch->getCapacity());
    }

    public function testGetBufferRoundtrip(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');
        $debitId = Uint128::fromString('2000000000000000000000000000001');
        $creditId = Uint128::fromString('3000000000000000000000000000002');
        $amount = Uint128::fromString('4000000000000000000000000000003');
        $ud128 = Uint128::fromString('5000000000000000000000000000004');

        $batch = new TransferBatch(10);
        $batch->add();
        $batch->setId($id);
        $batch->setDebitAccountId($debitId);
        $batch->setCreditAccountId($creditId);
        $batch->setAmount($amount);
        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setTimeout(3600);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $buffer = $batch->getBuffer();
        $restored = TransferBatch::fromBuffer($buffer);

        $restored->rewind();
        $this->assertTrue($id->equals($restored->getId()));
        $this->assertTrue($debitId->equals($restored->getDebitAccountId()));
        $this->assertTrue($creditId->equals($restored->getCreditAccountId()));
        $this->assertTrue($amount->equals($restored->getAmount()));
        $this->assertTrue($ud128->equals($restored->getUserData128()));
        $this->assertSame(0xDEADBEEF, $restored->getUserData64());
        $this->assertSame(3600, $restored->getTimeout());
        $this->assertSame(777, $restored->getLedger());
        $this->assertSame(100, $restored->getCode());
        $this->assertSame(0b1010, $restored->getFlags());
    }

    public function testNavigationAfterSet(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));
        $batch->add();
        $batch->setId(Uint128::fromString('2000000000000000000000000000000'));

        $batch->rewind();
        $first = $batch->getId();
        $batch->next();
        $second = $batch->getId();
        $batch->prev();
        $firstAgain = $batch->getId();

        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($first),
        );
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000000')->equals($second),
        );
        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($firstAgain),
        );
    }

    public function testBinaryHelperRoundtrip(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));
        $batch->setDebitAccountId(Uint128::fromString('2000000000000000000000000000001'));
        $batch->setCreditAccountId(Uint128::fromString('3000000000000000000000000000002'));
        $batch->setAmount(Uint128::fromString('4000000000000000000000000000003'));
        $batch->setPendingId(Uint128::fromString('5000000000000000000000000000004'));
        $batch->setUserData128(Uint128::fromString('6000000000000000000000000000005'));
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setTimeout(3600);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $buffer = $batch->getBuffer();
        $unpacked = BinaryHelper::unpackTransfer($buffer);

        $this->assertSame(
            '1000000000000000000000000000000',
            Uint128::fromBytes($unpacked['id'])->toString(),
        );
        $this->assertSame(
            '2000000000000000000000000000001',
            Uint128::fromBytes($unpacked['debit_account_id'])->toString(),
        );
        $this->assertSame(
            '3000000000000000000000000000002',
            Uint128::fromBytes($unpacked['credit_account_id'])->toString(),
        );
        $this->assertSame(
            '4000000000000000000000000000003',
            Uint128::fromBytes($unpacked['amount'])->toString(),
        );
        $this->assertSame(
            '5000000000000000000000000000004',
            Uint128::fromBytes($unpacked['pending_id'])->toString(),
        );
        $this->assertSame(
            '6000000000000000000000000000005',
            Uint128::fromBytes($unpacked['user_data_128'])->toString(),
        );
        $this->assertSame(0xDEADBEEF, $unpacked['user_data_64']);
        $this->assertSame(0xCAFEBABE, $unpacked['user_data_32']);
        $this->assertSame(3600, $unpacked['timeout']);
        $this->assertSame(777, $unpacked['ledger']);
        $this->assertSame(100, $unpacked['code']);
        $this->assertSame(0b1010, $unpacked['flags']);
    }

    public function testMultipleTransfersInBatch(): void
    {
        $batch = new TransferBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));
        $batch->setDebitAccountId(Uint128::fromString('2000000000000000000000000000001'));
        $batch->add();
        $batch->setId(Uint128::fromString('3000000000000000000000000000002'));
        $batch->setDebitAccountId(Uint128::fromString('4000000000000000000000000000003'));
        $batch->add();
        $batch->setId(Uint128::fromString('5000000000000000000000000000004'));
        $batch->setDebitAccountId(Uint128::fromString('6000000000000000000000000000005'));

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($batch->getId()),
        );
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000001')->equals($batch->getDebitAccountId()),
        );
        $batch->next();
        $this->assertTrue(
            Uint128::fromString('3000000000000000000000000000002')->equals($batch->getId()),
        );
        $this->assertTrue(
            Uint128::fromString('4000000000000000000000000000003')->equals($batch->getDebitAccountId()),
        );
        $batch->next();
        $this->assertTrue(
            Uint128::fromString('5000000000000000000000000000004')->equals($batch->getId()),
        );
        $this->assertTrue(
            Uint128::fromString('6000000000000000000000000000005')->equals($batch->getDebitAccountId()),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function invalidIntegerValues(): iterable
    {
        yield 'user_data_64 negative' => ['user_data_64', -1];
        yield 'user_data_32 negative' => ['user_data_32', -1];
        yield 'user_data_32 overflow' => ['user_data_32', 0x1_0000_0000];
        yield 'timeout negative' => ['timeout', -1];
        yield 'timeout overflow' => ['timeout', 0x1_0000_0000];
        yield 'ledger negative' => ['ledger', -1];
        yield 'ledger overflow' => ['ledger', 0x1_0000_0000];
        yield 'code negative' => ['code', -1];
        yield 'code overflow' => ['code', 0x1_0000];
        yield 'flags negative' => ['flags', -1];
        yield 'flags overflow' => ['flags', 0x1_0000];
    }

    #[DataProvider('invalidIntegerValues')]
    public function testIntegerSettersRejectOutOfRangeValues(string $field, int $value): void
    {
        $batch = new TransferBatch(1);
        $batch->add();

        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(\sprintf('"%s"', $field));

        $batch->{'set' . \str_replace('_', '', \ucwords($field, '_'))}($value);
    }

    public function testIntegerSettersAcceptBoundaryValues(): void
    {
        $batch = new TransferBatch(1);
        $batch->add();

        $batch->setUserData64(PHP_INT_MAX);
        $batch->setUserData32(0xFFFFFFFF);
        $batch->setTimeout(0xFFFFFFFF);
        $batch->setLedger(0xFFFFFFFF);
        $batch->setCode(0xFFFF);
        $batch->setFlags(0xFFFF);

        $this->assertSame(PHP_INT_MAX, $batch->getUserData64());
        $this->assertSame(0xFFFFFFFF, $batch->getUserData32());
        $this->assertSame(0xFFFFFFFF, $batch->getTimeout());
        $this->assertSame(0xFFFFFFFF, $batch->getLedger());
        $this->assertSame(0xFFFF, $batch->getCode());
        $this->assertSame(0xFFFF, $batch->getFlags());
    }

    public function testIntegerSettersAcceptZero(): void
    {
        $batch = new TransferBatch(1);
        $batch->add();

        $batch->setUserData64(0);
        $batch->setUserData32(0);
        $batch->setTimeout(0);
        $batch->setLedger(0);
        $batch->setCode(0);
        $batch->setFlags(0);

        $this->assertSame(0, $batch->getUserData64());
        $this->assertSame(0, $batch->getUserData32());
        $this->assertSame(0, $batch->getTimeout());
        $this->assertSame(0, $batch->getLedger());
        $this->assertSame(0, $batch->getCode());
        $this->assertSame(0, $batch->getFlags());
    }

    public function testSettersThrowBeforeAdd(): void
    {
        $batch = new TransferBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot write field on ' . TransferBatch::class);

        $batch->setId(Uint128::fromString('1'));
    }

    public function testGettersThrowBeforeAdd(): void
    {
        $batch = new TransferBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . TransferBatch::class);

        $batch->getId();
    }

    public function testSettersAndGettersThrowOnEmptyFromBuffer(): void
    {
        $batch = TransferBatch::fromBuffer('');

        $this->expectException(InvalidBatchCursorException::class);
        $batch->setLedger(1);
    }

    public function testBufferRemainsEmptyAfterFailedSetterBeforeAdd(): void
    {
        $batch = new TransferBatch(10);

        try {
            $batch->setAmount(Uint128::fromString('1'));
            $this->fail('Expected InvalidBatchCursorException');
        } catch (InvalidBatchCursorException) {
        }

        $this->assertSame(0, $batch->getLength());
        $this->assertSame('', $batch->getBuffer());
    }
}
