<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\ChangeEventsFilterBatch;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class ChangeEventsFilterBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new ChangeEventsFilterBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new ChangeEventsFilterBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new ChangeEventsFilterBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetAccountId(): void
    {
        $batch = new ChangeEventsFilterBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setAccountId($id);

        $this->assertTrue($id->equals($batch->getAccountId()));
    }

    public function testMultipleAccountIds(): void
    {
        $batch = new ChangeEventsFilterBatch(10);

        $id0 = Uint128::fromString('1000000000000000000000000000000');
        $id1 = Uint128::fromString('2000000000000000000000000000000');

        $batch->add();
        $batch->setAccountId($id0);
        $batch->add();
        $batch->setAccountId($id1);

        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($id0->equals($batch->getAccountId()));
        $batch->next();
        $this->assertTrue($id1->equals($batch->getAccountId()));
    }

    public function testDefaultAccountIdIsZero(): void
    {
        $batch = new ChangeEventsFilterBatch(10);
        $batch->add();

        $this->assertTrue(Uint128::zero()->equals($batch->getAccountId()));
    }

    public function testGetterThrowsBeforeAdd(): void
    {
        $batch = new ChangeEventsFilterBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . ChangeEventsFilterBatch::class);

        $batch->getAccountId();
    }

    public function testSetterThrowsBeforeAdd(): void
    {
        $batch = new ChangeEventsFilterBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot write field on ' . ChangeEventsFilterBatch::class);

        $batch->setAccountId(Uint128::fromString('1'));
    }

    public function testBufferRoundtrip(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');

        $batch = new ChangeEventsFilterBatch(10);
        $batch->add();
        $batch->setAccountId($id);

        $buffer = $batch->getBuffer();
        $this->assertSame(16, \strlen($buffer));

        $restored = Uint128::fromBytes($buffer);
        $this->assertTrue($id->equals($restored));
    }

    public function testSetAndGetAtPosition(): void
    {
        $batch = new ChangeEventsFilterBatch(10);
        $batch->add();
        $batch->add();
        $batch->add();

        $id0 = Uint128::fromString('1000000000000000000000000000000');
        $id1 = Uint128::fromString('2000000000000000000000000000000');

        $batch->rewind();
        $batch->setAccountId($id0);
        $batch->next();
        $batch->setAccountId($id1);

        $batch->rewind();
        $this->assertTrue($id0->equals($batch->getAccountId()));
        $batch->next();
        $this->assertTrue($id1->equals($batch->getAccountId()));
    }

    public function testBufferRemainsEmptyAfterFailedSetterBeforeAdd(): void
    {
        $batch = new ChangeEventsFilterBatch(10);

        try {
            $batch->setAccountId(Uint128::fromString('1'));
            $this->fail('Expected InvalidBatchCursorException');
        } catch (InvalidBatchCursorException) {
        }

        $this->assertSame(0, $batch->getLength());
        $this->assertSame('', $batch->getBuffer());
    }
}
