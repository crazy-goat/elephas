<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new AccountBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new AccountBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetId(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setId($id);

        $this->assertTrue($id->equals($batch->getId()));
    }

    public function testSetAndGetIdAtPosition(): void
    {
        $batch = new AccountBatch(10);
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

    public function testSetAndGetDebitsPending(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setDebitsPending($value);

        $this->assertTrue($value->equals($batch->getDebitsPending()));
    }

    public function testSetAndGetDebitsPosted(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setDebitsPosted($value);

        $this->assertTrue($value->equals($batch->getDebitsPosted()));
    }

    public function testSetAndGetCreditsPending(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setCreditsPending($value);

        $this->assertTrue($value->equals($batch->getCreditsPending()));
    }

    public function testSetAndGetCreditsPosted(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setCreditsPosted($value);

        $this->assertTrue($value->equals($batch->getCreditsPosted()));
    }

    public function testSetAndGetUserData128(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setUserData128($value);

        $this->assertTrue($value->equals($batch->getUserData128()));
    }

    public function testSetAndGetUserData64(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $batch->setUserData64(0xDEADBEEF);
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
    }

    public function testSetAndGetUserData32(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $batch->setUserData32(0xCAFEBABE);
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
    }

    public function testSetAndGetLedger(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $batch->setLedger(777);
        $this->assertSame(777, $batch->getLedger());
    }

    public function testSetAndGetCode(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $batch->setCode(100);
        $this->assertSame(100, $batch->getCode());
    }

    public function testSetAndGetFlags(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $batch->setFlags(0b1010);
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testGetTimestampInitiallyZero(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $this->assertSame(0, $batch->getTimestamp());
    }

    public function testMultipleFieldsOnSameItem(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $ud128 = Uint128::fromString('2000000000000000000000000000000');

        $batch->setId($id);
        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $this->assertTrue($id->equals($batch->getId()));
        $this->assertTrue($ud128->equals($batch->getUserData128()));
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
        $this->assertSame(777, $batch->getLedger());
        $this->assertSame(100, $batch->getCode());
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testIsFoundReturnsTrueForNonZeroId(): void
    {
        $batch = new AccountBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));

        $this->assertTrue($batch->isFound());
    }

    public function testIsFoundReturnsFalseForZeroId(): void
    {
        $batch = AccountBatch::fromBuffer(\str_repeat("\0", 128));
        $batch->rewind();

        $this->assertFalse($batch->isFound());
    }

    public function testIsFoundOnEmptyBufferReturnsFalse(): void
    {
        $batch = AccountBatch::fromBuffer('');

        $this->assertFalse($batch->isFound());
    }

    public function testIsFoundMixedBatch(): void
    {
        $source = new AccountBatch(3);
        $source->add();
        $source->setId(Uint128::fromString('1000000000000000000000000000000'));
        $source->add();
        $source->setId(Uint128::fromString('2000000000000000000000000000000'));

        $buffer = $source->getBuffer() . \str_repeat("\0", 128);
        $batch = AccountBatch::fromBuffer($buffer);

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
        $source = new AccountBatch(3);
        $source->add();
        $source->setId(Uint128::fromString('1000000000000000000000000000000'));
        $source->add();
        $source->setId(Uint128::fromString('2000000000000000000000000000000'));

        $buffer = $source->getBuffer();
        $restored = AccountBatch::fromBuffer($buffer);

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
        $buffer = \str_repeat("\0", BinaryHelper::ACCOUNT_SIZE * 5);
        $batch = AccountBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testFromBufferRejectsMalformedBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'AccountBatch buffer size must be a multiple of 128 bytes, got 100 bytes',
        );

        AccountBatch::fromBuffer(\str_repeat("\0", 100));
    }

    public function testFromBufferAcceptsEmptyBuffer(): void
    {
        $batch = AccountBatch::fromBuffer('');

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(0, $batch->getCapacity());
    }

    public function testGetBufferRoundtrip(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');
        $ud128 = Uint128::fromString('2000000000000000000000000000000');

        $batch = new AccountBatch(10);
        $batch->add();
        $batch->setId($id);
        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $buffer = $batch->getBuffer();
        $restored = AccountBatch::fromBuffer($buffer);

        $restored->rewind();
        $this->assertTrue($id->equals($restored->getId()));
        $this->assertTrue($ud128->equals($restored->getUserData128()));
        $this->assertSame(0xDEADBEEF, $restored->getUserData64());
        $this->assertSame(777, $restored->getLedger());
        $this->assertSame(100, $restored->getCode());
        $this->assertSame(0b1010, $restored->getFlags());
    }

    public function testNavigationAfterSet(): void
    {
        $batch = new AccountBatch(10);
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
        $batch = new AccountBatch(10);
        $batch->add();
        $batch->setId(Uint128::fromString('1000000000000000000000000000000'));
        $batch->setDebitsPending(Uint128::fromString('1000000000000000000000000000001'));
        $batch->setDebitsPosted(Uint128::fromString('1000000000000000000000000000002'));
        $batch->setCreditsPending(Uint128::fromString('1000000000000000000000000000003'));
        $batch->setCreditsPosted(Uint128::fromString('1000000000000000000000000000004'));
        $batch->setUserData128(Uint128::fromString('1000000000000000000000000000005'));
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setFlags(0b1010);

        $buffer = $batch->getBuffer();
        $unpacked = BinaryHelper::unpackAccount($buffer);

        $this->assertSame(
            '1000000000000000000000000000000',
            Uint128::fromBytes($unpacked['id'])->toString(),
        );
        $this->assertSame(
            '1000000000000000000000000000001',
            Uint128::fromBytes($unpacked['debits_pending'])->toString(),
        );
        $this->assertSame(
            '1000000000000000000000000000002',
            Uint128::fromBytes($unpacked['debits_posted'])->toString(),
        );
        $this->assertSame(
            '1000000000000000000000000000003',
            Uint128::fromBytes($unpacked['credits_pending'])->toString(),
        );
        $this->assertSame(
            '1000000000000000000000000000004',
            Uint128::fromBytes($unpacked['credits_posted'])->toString(),
        );
        $this->assertSame(
            '1000000000000000000000000000005',
            Uint128::fromBytes($unpacked['user_data_128'])->toString(),
        );
        $this->assertSame(0xDEADBEEF, $unpacked['user_data_64']);
        $this->assertSame(0xCAFEBABE, $unpacked['user_data_32']);
        $this->assertSame(777, $unpacked['ledger']);
        $this->assertSame(100, $unpacked['code']);
        $this->assertSame(0b1010, $unpacked['flags']);
    }

    public function testAddAtPositionPreservesData(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch = new AccountBatch(10);
        $batch->add();
        $batch->setId($id);

        $batch->add();
        $batch->setId(Uint128::fromString('2000000000000000000000000000000'));

        $batch->add();
        $batch->setId(Uint128::fromString('3000000000000000000000000000000'));

        $batch->rewind();
        $this->assertTrue($id->equals($batch->getId()));
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function invalidIntegerValues(): iterable
    {
        yield 'user_data_64 negative' => ['user_data_64', -1];
        yield 'user_data_32 negative' => ['user_data_32', -1];
        yield 'user_data_32 overflow' => ['user_data_32', 0x1_0000_0000];
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
        $batch = new AccountBatch(1);
        $batch->add();

        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(\sprintf('"%s"', $field));

        $batch->{'set' . \str_replace('_', '', \ucwords($field, '_'))}($value);
    }

    public function testIntegerSettersAcceptBoundaryValues(): void
    {
        $batch = new AccountBatch(1);
        $batch->add();

        $batch->setUserData64(PHP_INT_MAX);
        $batch->setUserData32(0xFFFFFFFF);
        $batch->setLedger(0xFFFFFFFF);
        $batch->setCode(0xFFFF);
        $batch->setFlags(0xFFFF);

        $this->assertSame(PHP_INT_MAX, $batch->getUserData64());
        $this->assertSame(0xFFFFFFFF, $batch->getUserData32());
        $this->assertSame(0xFFFFFFFF, $batch->getLedger());
        $this->assertSame(0xFFFF, $batch->getCode());
        $this->assertSame(0xFFFF, $batch->getFlags());
    }

    public function testIntegerSettersAcceptZero(): void
    {
        $batch = new AccountBatch(1);
        $batch->add();

        $batch->setUserData64(0);
        $batch->setUserData32(0);
        $batch->setLedger(0);
        $batch->setCode(0);
        $batch->setFlags(0);

        $this->assertSame(0, $batch->getUserData64());
        $this->assertSame(0, $batch->getUserData32());
        $this->assertSame(0, $batch->getLedger());
        $this->assertSame(0, $batch->getCode());
        $this->assertSame(0, $batch->getFlags());
    }
}
