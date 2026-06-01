<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use PHPUnit\Framework\TestCase;

class CreateTransferResultBatchTest extends TestCase
{
    public function testConstructor(): void
    {
        $batch = new CreateTransferResultBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
    }

    public function testIsReadOnly(): void
    {
        $batch = new CreateTransferResultBatch(10);

        $this->assertTrue($batch->isReadOnly());
    }

    public function testAddThrows(): void
    {
        $batch = new CreateTransferResultBatch(10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CreateTransferResultBatch is read-only');

        $batch->add();
    }

    public function testGetResultReturnsCreatedStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 12345, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertTrue($result->isCreated());
        $this->assertSame(CreateTransferStatus::CREATED, $result->getStatus());
    }

    public function testGetResultReturnsErrorStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, 1, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertFalse($result->isCreated());
        $this->assertSame(CreateTransferStatus::LINKED_EVENT_FAILED, $result->getStatus());
    }

    public function testGetResultReturnsCorrectIdAsTimestamp(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 999888777, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertSame('999888777', $result->getId()->toString());
    }

    public function testMultipleResults(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 100, 0xFFFFFFFF, 0),
            \pack('PVV', 200, 2, 0),
        ]);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($batch->getResult()->isCreated());

        $batch->next();
        $this->assertFalse($batch->getResult()->isCreated());
        $this->assertSame(CreateTransferStatus::LINKED_EVENT_CHAIN_OPEN, $batch->getResult()->getStatus());
    }

    public function testFromBufferSetsCorrectLength(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::CREATE_TRANSFER_RESULT_SIZE * 5);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testCountIsZeroWhenEmpty(): void
    {
        $batch = new CreateTransferResultBatch(10);

        $this->assertSame(0, $batch->count());
    }

    public function testCountIsCorrectAfterFromBuffer(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::CREATE_TRANSFER_RESULT_SIZE * 3);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->count());
    }
}
