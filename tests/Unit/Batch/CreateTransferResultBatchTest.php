<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Exception\UnknownStatusException;
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

    public function testGetResultReturnsCorrectTimestamp(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 999888777, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertSame(999888777, $result->getTimestamp());
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

    public function testFromBufferRejectsMalformedBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'CreateTransferResultBatch buffer size must be a multiple of 16 bytes, got 5 bytes',
        );

        CreateTransferResultBatch::fromBuffer(\str_repeat("\0", 5));
    }

    public function testFromBufferAcceptsEmptyBuffer(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer('');

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(0, $batch->getCapacity());
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

    public function testGetResultThrowsUnknownStatusExceptionForUnknownStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, 999, 0),
        );

        $batch->rewind();

        $this->expectException(UnknownStatusException::class);
        $this->expectExceptionMessage('Unknown CrazyGoat\Elephas\CreateTransferStatus value 999');
        $this->expectExceptionMessage('Known values:');
        $batch->getResult();
    }

    public function testGetResultTimestampCorrespondsToPosition(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 111, 0xFFFFFFFF, 0),
            \pack('PVV', 222, 0xFFFFFFFF, 0),
            \pack('PVV', 333, 0xFFFFFFFF, 0),
        ]);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertSame(111, $batch->getResult()->getTimestamp());

        $batch->next();
        $this->assertSame(222, $batch->getResult()->getTimestamp());

        $batch->next();
        $this->assertSame(333, $batch->getResult()->getTimestamp());
    }

    public function testGetResultReturnsTimestampFromSuccessfulResult(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 42, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertSame(42, $result->getTimestamp());
        $this->assertTrue($result->isCreated());
    }

    public function testGetResultReturnsTimestampFromErrorResult(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, CreateTransferStatus::ID_MUST_NOT_BE_ZERO->value, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertSame(0, $result->getTimestamp());
        $this->assertFalse($result->isCreated());
        $this->assertSame(CreateTransferStatus::ID_MUST_NOT_BE_ZERO, $result->getStatus());
    }

    public function testGetResultThrowsOnEmptyBatch(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer('');

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . CreateTransferResultBatch::class);

        $batch->getResult();
    }

    public function testGetResultThrowsUnknownStatusExceptionForZeroStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, 0, 0),
        );

        $batch->rewind();

        $this->expectException(UnknownStatusException::class);
        $this->expectExceptionMessage('Unknown CrazyGoat\Elephas\CreateTransferStatus value 0');
        $batch->getResult();
    }

    public function testGetResultThrowsUnknownStatusExceptionForLargeStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, 0x7FFFFFFF, 0),
        );

        $batch->rewind();

        $this->expectException(UnknownStatusException::class);
        $this->expectExceptionMessage('Unknown CrazyGoat\Elephas\CreateTransferStatus value');
        $batch->getResult();
    }

    public function testAllCreatedResults(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 100, 0xFFFFFFFF, 0),
            \pack('PVV', 200, 0xFFFFFFFF, 0),
            \pack('PVV', 300, 0xFFFFFFFF, 0),
        ]);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($batch->getResult()->isCreated());
        $batch->next();
        $this->assertTrue($batch->getResult()->isCreated());
        $batch->next();
        $this->assertTrue($batch->getResult()->isCreated());
    }

    public function testAllErrorResults(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 0, 1, 0),
            \pack('PVV', 0, 10, 0),
            \pack('PVV', 0, 36, 0),
        ]);
        $batch = CreateTransferResultBatch::fromBuffer($buffer);

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertFalse($batch->getResult()->isCreated());
        $this->assertSame(CreateTransferStatus::LINKED_EVENT_FAILED, $batch->getResult()->getStatus());

        $batch->next();
        $this->assertFalse($batch->getResult()->isCreated());
        $this->assertSame(CreateTransferStatus::DEBITS_ACCOUNTS_MUST_DIFFER, $batch->getResult()->getStatus());

        $batch->next();
        $this->assertFalse($batch->getResult()->isCreated());
        $this->assertSame(CreateTransferStatus::IMPORTED_EVENT_TIMESTAMP_MUST_BE_IN_THE_FUTURE, $batch->getResult()->getStatus());
    }

    public function testZeroTimestampWithCreatedStatus(): void
    {
        $batch = CreateTransferResultBatch::fromBuffer(
            \pack('PVV', 0, 0xFFFFFFFF, 0),
        );

        $batch->rewind();
        $result = $batch->getResult();

        $this->assertSame(0, $result->getTimestamp());
        $this->assertTrue($result->isCreated());
    }

    public function testFromBufferRejectsBufferWithExtraByte(): void
    {
        $validBuffer = \pack('PVV', 100, 0xFFFFFFFF, 0);
        $corruptedBuffer = $validBuffer . "\x01";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 16 bytes');

        CreateTransferResultBatch::fromBuffer($corruptedBuffer);
    }
}
