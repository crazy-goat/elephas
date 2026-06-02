<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\AccountFilterBatch;
use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountFilterBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new AccountFilterBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new AccountFilterBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetAccountId(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setAccountId($id);

        $this->assertTrue($id->equals($batch->getAccountId()));
    }

    public function testSetAndGetUserData128(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $value = Uint128::fromString('2000000000000000000000000000000');
        $batch->setUserData128($value);

        $this->assertTrue($value->equals($batch->getUserData128()));
    }

    public function testSetAndGetUserData64(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setUserData64(0xDEADBEEF);
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
    }

    public function testSetAndGetUserData32(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setUserData32(0xCAFEBABE);
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
    }

    public function testSetAndGetCode(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setCode(100);
        $this->assertSame(100, $batch->getCode());
    }

    public function testSetAndGetTimestampMin(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setTimestampMin(9876543210);
        $this->assertSame(9876543210, $batch->getTimestampMin());
    }

    public function testSetAndGetTimestampMax(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setTimestampMax(9876543210);
        $this->assertSame(9876543210, $batch->getTimestampMax());
    }

    public function testSetAndGetLimit(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setLimit(100);
        $this->assertSame(100, $batch->getLimit());
    }

    public function testSetAndGetFlags(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $batch->setFlags(0b1010);
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testMultipleFieldsOnSameItem(): void
    {
        $batch = new AccountFilterBatch(10);
        $batch->add();

        $accountId = Uint128::fromString('1000000000000000000000000000000');
        $ud128 = Uint128::fromString('2000000000000000000000000000000');

        $batch->setAccountId($accountId);
        $batch->setUserData128($ud128);
        $batch->setUserData64(0xDEADBEEF);
        $batch->setUserData32(0xCAFEBABE);
        $batch->setCode(100);
        $batch->setTimestampMin(1111);
        $batch->setTimestampMax(2222);
        $batch->setLimit(50);
        $batch->setFlags(0b1010);

        $this->assertTrue($accountId->equals($batch->getAccountId()));
        $this->assertTrue($ud128->equals($batch->getUserData128()));
        $this->assertSame(0xDEADBEEF, $batch->getUserData64());
        $this->assertSame(0xCAFEBABE, $batch->getUserData32());
        $this->assertSame(100, $batch->getCode());
        $this->assertSame(1111, $batch->getTimestampMin());
        $this->assertSame(2222, $batch->getTimestampMax());
        $this->assertSame(50, $batch->getLimit());
        $this->assertSame(0b1010, $batch->getFlags());
    }

    public function testMultipleFiltersInBatch(): void
    {
        $batch = new AccountFilterBatch(10);

        $batch->add();
        $batch->setAccountId(Uint128::fromString('1000000000000000000000000000000'));
        $batch->add();
        $batch->setAccountId(Uint128::fromString('2000000000000000000000000000000'));

        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertTrue(
            Uint128::fromString('1000000000000000000000000000000')->equals($batch->getAccountId()),
        );
        $batch->next();
        $this->assertTrue(
            Uint128::fromString('2000000000000000000000000000000')->equals($batch->getAccountId()),
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
        yield 'code negative' => ['code', -1];
        yield 'code overflow' => ['code', 0x1_0000];
        yield 'timestamp_min negative' => ['timestamp_min', -1];
        yield 'timestamp_max negative' => ['timestamp_max', -1];
        yield 'limit negative' => ['limit', -1];
        yield 'limit overflow' => ['limit', 0x1_0000_0000];
        yield 'flags negative' => ['flags', -1];
        yield 'flags overflow' => ['flags', 0x1_0000_0000];
    }

    #[DataProvider('invalidIntegerValues')]
    public function testIntegerSettersRejectOutOfRangeValues(string $field, int $value): void
    {
        $batch = new AccountFilterBatch(1);
        $batch->add();

        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(\sprintf('"%s"', $field));

        $batch->{'set' . \str_replace('_', '', \ucwords($field, '_'))}($value);
    }

    public function testIntegerSettersAcceptBoundaryValues(): void
    {
        $batch = new AccountFilterBatch(1);
        $batch->add();

        $batch->setUserData64(PHP_INT_MAX);
        $batch->setUserData32(0xFFFFFFFF);
        $batch->setCode(0xFFFF);
        $batch->setTimestampMin(PHP_INT_MAX);
        $batch->setTimestampMax(PHP_INT_MAX);
        $batch->setLimit(0xFFFFFFFF);
        $batch->setFlags(0xFFFFFFFF);

        $this->assertSame(PHP_INT_MAX, $batch->getUserData64());
        $this->assertSame(0xFFFFFFFF, $batch->getUserData32());
        $this->assertSame(0xFFFF, $batch->getCode());
        $this->assertSame(PHP_INT_MAX, $batch->getTimestampMin());
        $this->assertSame(PHP_INT_MAX, $batch->getTimestampMax());
        $this->assertSame(0xFFFFFFFF, $batch->getLimit());
        $this->assertSame(0xFFFFFFFF, $batch->getFlags());
    }

    public function testSettersThrowBeforeAdd(): void
    {
        $batch = new AccountFilterBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot write field on ' . AccountFilterBatch::class);

        $batch->setAccountId(Uint128::fromString('1'));
    }

    public function testGettersThrowBeforeAdd(): void
    {
        $batch = new AccountFilterBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . AccountFilterBatch::class);

        $batch->getAccountId();
    }
}
