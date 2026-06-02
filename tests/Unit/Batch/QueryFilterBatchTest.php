<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\QueryFilterBatch;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class QueryFilterBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new QueryFilterBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new QueryFilterBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetUserData128(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $value = Uint128::fromString('1000000000000000000000000000000');
        $batch->setUserData128($value);

        $this->assertTrue($value->equals($batch->getUserData128()));
    }

    public function testSetAndGetUserData64(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setUserData64(0xDEADBEEF);
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
    }

    public function testSetAndGetUserData32(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setUserData32(0xCAFEBABE);
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
    }

    public function testSetAndGetLedger(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setLedger(777);
        $this->assertSame(777, $batch->getLedger());
    }

    public function testSetAndGetCode(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setCode(100);
        $this->assertSame(100, $batch->getCode());
    }

    public function testSetAndGetTimestampMin(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setTimestampMin(9876543210);
        $this->assertSame(9876543210, $batch->getTimestampMin());
    }

    public function testSetAndGetTimestampMax(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setTimestampMax(9876543210);
        $this->assertSame(9876543210, $batch->getTimestampMax());
    }

    public function testSetAndGetLimit(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setLimit(100);
        $this->assertSame(100, $batch->getLimit());
    }

    public function testSetAndGetFlags(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $batch->setFlags(0b1010);
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testMultipleFieldsOnSameItem(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();

        $ud128 = Uint128::fromString('1000000000000000000000000000000');

        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setTimestampMin(1111);
        $batch->setTimestampMax(2222);
        $batch->setLimit(50);
        $batch->setFlags(0b1010);

        $this->assertTrue($ud128->equals($batch->getUserData128()));
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
        $this->assertSame(777, $batch->getLedger());
        $this->assertSame(100, $batch->getCode());
        $this->assertSame(1111, $batch->getTimestampMin());
        $this->assertSame(2222, $batch->getTimestampMax());
        $this->assertSame(50, $batch->getLimit());
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testMultipleFiltersInBatch(): void
    {
        $batch = new QueryFilterBatch(10);

        $batch->add();
        $batch->setUserData128(Uint128::fromString('1000000000000000000000000000000'));
        $batch->add();
        $batch->setUserData128(Uint128::fromString('2000000000000000000000000000000'));

        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($batch->getUserData128()),
        );
        $batch->next();
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000000')->equals($batch->getUserData128()),
        );
    }

    public function testFromBufferSetsCorrectLength(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::QUERY_FILTER_SIZE * 5);
        $batch = QueryFilterBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testFromBufferRejectsMalformedBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'QueryFilterBatch buffer size must be a multiple of 64 bytes, got 50 bytes',
        );

        QueryFilterBatch::fromBuffer(\str_repeat("\0", 50));
    }

    public function testFromBufferAcceptsEmptyBuffer(): void
    {
        $batch = QueryFilterBatch::fromBuffer('');

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(0, $batch->getCapacity());
    }

    public function testBinaryHelperRoundtrip(): void
    {
        $batch = new QueryFilterBatch(10);
        $batch->add();
        $batch->setUserData128(Uint128::fromString('1000000000000000000000000000000'));
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setLedger(777);
        $batch->setCode(100);
        $batch->setTimestampMin(1111);
        $batch->setTimestampMax(2222);
        $batch->setLimit(50);
        $batch->setFlags(0b1010);

        $buffer = $batch->getBuffer();
        $unpacked = BinaryHelper::unpackQueryFilter($buffer);

        $this->assertSame(
            '1000000000000000000000000000000',
            Uint128::fromBytes($unpacked['user_data_128'])->toString(),
        );
        $this->assertSame(0xDEADBEEF, $unpacked['user_data_64']);
        $this->assertSame(0xCAFEBABE, $unpacked['user_data_32']);
        $this->assertSame(777, $unpacked['ledger']);
        $this->assertSame(100, $unpacked['code']);
        $this->assertSame(1111, $unpacked['timestamp_min']);
        $this->assertSame(2222, $unpacked['timestamp_max']);
        $this->assertSame(50, $unpacked['limit']);
        $this->assertSame(0b1010, $unpacked['flags']);
    }
}
