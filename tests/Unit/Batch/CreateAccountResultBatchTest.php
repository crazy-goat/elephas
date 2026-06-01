<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use PHPUnit\Framework\TestCase;

class CreateAccountResultBatchTest extends TestCase
{
    public function testConstructor(): void
    {
        $batch = new CreateAccountResultBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
    }

    public function testIsReadOnly(): void
    {
        $batch = new CreateAccountResultBatch(10);

        $this->assertTrue($batch->isReadOnly());
    }

    public function testAddThrows(): void
    {
        $batch = new CreateAccountResultBatch(10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CreateAccountResultBatch is read-only');

        $batch->add();
    }

    public function testGetResultReturnsCreatedStatus(): void
    {
        $batch = CreateAccountResultBatch::fromBuffer(
            \pack('PVV', 12345, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertTrue($result->isCreated());
        $this->assertSame(CreateAccountStatus::CREATED, $result->getStatus());
    }

    public function testGetResultReturnsErrorStatus(): void
    {
        $batch = CreateAccountResultBatch::fromBuffer(
            \pack('PVV', 0, 1, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertFalse($result->isCreated());
        $this->assertSame(CreateAccountStatus::LINKED_EVENT_FAILED, $result->getStatus());
    }

    public function testGetResultReturnsCorrectIdAsTimestamp(): void
    {
        $batch = CreateAccountResultBatch::fromBuffer(
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
        $batch = CreateAccountResultBatch::fromBuffer($buffer);

        $this->assertSame(2, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($batch->getResult()->isCreated());

        $batch->next();
        $this->assertFalse($batch->getResult()->isCreated());
        $this->assertSame(CreateAccountStatus::LINKED_EVENT_CHAIN_OPEN, $batch->getResult()->getStatus());
    }

    public function testFromBufferSetsCorrectLength(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE * 5);
        $batch = CreateAccountResultBatch::fromBuffer($buffer);

        $this->assertSame(5, $batch->getLength());
        $this->assertSame(5, $batch->getCapacity());
    }

    public function testCountIsZeroWhenEmpty(): void
    {
        $batch = new CreateAccountResultBatch(10);

        $this->assertSame(0, $batch->count());
    }

    public function testCountIsCorrectAfterFromBuffer(): void
    {
        $buffer = \str_repeat("\0", BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE * 3);
        $batch = CreateAccountResultBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->count());
    }
}
