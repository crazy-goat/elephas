<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\AbstractBatch;
use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use PHPUnit\Framework\TestCase;

final class TestBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return 16;
    }
}

class AbstractBatchTest extends TestCase
{
    public function testConstructorSetsCapacity(): void
    {
        $batch = new TestBatch(10);

        $this->assertSame(10, $batch->getCapacity());
    }

    public function testConstructorDoesNotPreAllocateBuffer(): void
    {
        $batch = new TestBatch(10);

        $ref = new \ReflectionProperty(AbstractBatch::class, 'buffers');
        $this->assertSame([], $ref->getValue($batch));
    }

    public function testBufferIsLazilyAllocatedOnAdd(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertSame(16, \strlen($batch->getBuffer()));
        $batch->add();
        $this->assertSame(32, \strlen($batch->getBuffer()));
    }

    public function testLengthInitiallyZero(): void
    {
        $batch = new TestBatch(10);

        $this->assertSame(0, $batch->getLength());
    }

    public function testAddIncrementsLength(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
    }

    public function testAddSetsCurrentPosition(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new TestBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Batch capacity exceeded');

        $batch->add();
    }

    public function testNextAdvancesPosition(): void
    {
        $batch = new TestBatch(10);
        $batch->add();
        $batch->add();
        $batch->rewind();

        $result = $batch->next();

        $this->assertTrue($result);
        $this->assertTrue($batch->isValidPosition());
    }

    public function testNextReturnsFalseAtEnd(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $result = $batch->next();

        $this->assertFalse($result);
    }

    public function testPrevMovesBack(): void
    {
        $batch = new TestBatch(10);
        $batch->add();
        $batch->add();
        $batch->rewind();
        $batch->next();

        $result = $batch->prev();

        $this->assertTrue($result);
        $this->assertTrue($batch->isValidPosition());
    }

    public function testPrevReturnsFalseAtStart(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $batch->rewind();
        $result = $batch->prev();

        $this->assertFalse($result);
    }

    public function testRewindResetsPosition(): void
    {
        $batch = new TestBatch(10);
        $batch->add();
        $batch->add();
        $batch->next();

        $batch->rewind();

        $this->assertTrue($batch->isValidPosition());
    }

    public function testIsValidPositionTrueAfterAdd(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertTrue($batch->isValidPosition());
    }

    public function testIsValidPositionFalseWhenEmpty(): void
    {
        $batch = new TestBatch(10);

        $this->assertFalse($batch->isValidPosition());
    }

    public function testIsReadOnlyReturnsFalse(): void
    {
        $batch = new TestBatch(10);

        $this->assertFalse($batch->isReadOnly());
    }

    public function testGetBufferReturnsString(): void
    {
        $batch = new TestBatch(10);

        $this->assertSame('', $batch->getBuffer());
    }

    public function testGetBufferReturnsCorrectSize(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertSame(16, \strlen($batch->getBuffer()));
    }

    public function testToBytesAliasForGetBuffer(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->assertSame($batch->getBuffer(), $batch->toBytes());
    }

    public function testCountReturnsLength(): void
    {
        $batch = new TestBatch(10);
        $this->assertSame(0, $batch->count());

        $batch->add();
        $this->assertSame(1, $batch->count());

        $batch->add();
        $this->assertSame(2, $batch->count());
    }

    public function testAddTwoIncrementsPositionAndAllowsNavigation(): void
    {
        $batch = new TestBatch(10);

        $batch->add();
        $batch->add();
        $batch->add();

        $this->assertSame(3, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());

        $batch->rewind();
        $this->assertTrue($batch->next());
        $this->assertTrue($batch->next());
        $this->assertFalse($batch->next());
    }

    public function testRequireValidPositionThrowsOnEmptyBatch(): void
    {
        $batch = new TestBatch(10);

        $this->expectException(InvalidBatchCursorException::class);
        $this->expectExceptionMessage('Cannot read field on ' . TestBatch::class . ': cursor position 0 is outside the populated range [0, 0)');

        $this->callRequireValidPosition($batch, 'read field');
    }

    public function testRequireValidPositionPassesAfterAdd(): void
    {
        $batch = new TestBatch(10);
        $batch->add();

        $this->callRequireValidPosition($batch, 'read field');

        $this->assertTrue($batch->isValidPosition());
    }

    public function testRequireValidPositionPassesAfterAddAndContinuesToPass(): void
    {
        $batch = new TestBatch(10);
        $batch->add();
        $this->callRequireValidPosition($batch, 'read field');

        $batch->add();
        $this->callRequireValidPosition($batch, 'read field');

        $this->assertTrue($batch->isValidPosition());
    }

    public function testRequireValidPositionActionMessageIsIncluded(): void
    {
        $batch = new TestBatch(10);

        try {
            $this->callRequireValidPosition($batch, 'write custom action');
            $this->fail('Expected InvalidBatchCursorException');
        } catch (InvalidBatchCursorException $e) {
            $this->assertStringContainsString('write custom action', $e->getMessage());
            $this->assertStringContainsString(TestBatch::class, $e->getMessage());
        }
    }

    public function testExceptionImplementsElephasInterface(): void
    {
        $batch = new TestBatch(10);

        try {
            $this->callRequireValidPosition($batch, 'read field');
            $this->fail('Expected InvalidBatchCursorException');
        } catch (InvalidBatchCursorException $e) {
            $this->assertInstanceOf(\CrazyGoat\Elephas\Exception\ElephasExceptionInterface::class, $e);
        }
    }

    private function callRequireValidPosition(AbstractBatch $batch, string $action): void
    {
        $ref = new \ReflectionMethod(AbstractBatch::class, 'requireValidPosition');
        $ref->invoke($batch, $action);
    }
}
