<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Batch;

use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class IdBatchTest extends TestCase
{
    public function testConstructorCreatesEmptyBatch(): void
    {
        $batch = new IdBatch(10);

        $this->assertSame(0, $batch->getLength());
        $this->assertSame(10, $batch->getCapacity());
        $this->assertFalse($batch->isValidPosition());
    }

    public function testAddCreatesNewSlot(): void
    {
        $batch = new IdBatch(10);
        $batch->add();

        $this->assertSame(1, $batch->getLength());
        $this->assertTrue($batch->isValidPosition());
    }

    public function testAddBeyondCapacityThrows(): void
    {
        $batch = new IdBatch(2);
        $batch->add();
        $batch->add();

        $this->expectException(\OverflowException::class);

        $batch->add();
    }

    public function testSetAndGetId(): void
    {
        $batch = new IdBatch(10);
        $batch->add();

        $id = Uint128::fromString('1000000000000000000000000000000');
        $batch->setId($id);

        $this->assertTrue($id->equals($batch->getId()));
    }

    public function testMultipleIds(): void
    {
        $batch = new IdBatch(10);

        $id0 = Uint128::fromString('1000000000000000000000000000000');
        $id1 = Uint128::fromString('2000000000000000000000000000000');
        $id2 = Uint128::fromString('3000000000000000000000000000000');

        $batch->add();
        $batch->setId($id0);
        $batch->add();
        $batch->setId($id1);
        $batch->add();
        $batch->setId($id2);

        $this->assertSame(3, $batch->getLength());

        $batch->rewind();
        $this->assertTrue($id0->equals($batch->getId()));
        $batch->next();
        $this->assertTrue($id1->equals($batch->getId()));
        $batch->next();
        $this->assertTrue($id2->equals($batch->getId()));
    }

    public function testSetAndGetAtPosition(): void
    {
        $batch = new IdBatch(10);
        $batch->add();
        $batch->add();
        $batch->add();

        $id0 = Uint128::fromString('1000000000000000000000000000000');
        $id1 = Uint128::fromString('2000000000000000000000000000000');

        $batch->rewind();
        $batch->setId($id0);
        $batch->next();
        $batch->setId($id1);

        $batch->rewind();
        $this->assertTrue($id0->equals($batch->getId()));
        $batch->next();
        $this->assertTrue($id1->equals($batch->getId()));
    }

    public function testGetBufferRoundtrip(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');

        $batch = new IdBatch(10);
        $batch->add();
        $batch->setId($id);

        $buffer = $batch->getBuffer();
        $this->assertSame(16, \strlen($buffer));

        $restored = Uint128::fromBytes($buffer);
        $this->assertTrue($id->equals($restored));
    }

    public function testDefaultIdIsZero(): void
    {
        $batch = new IdBatch(10);
        $batch->add();

        $this->assertTrue(Uint128::zero()->equals($batch->getId()));
    }

    public function testBinaryHelperRoundtrip(): void
    {
        $id = Uint128::fromString('1000000000000000000000000000000');

        $batch = new IdBatch(10);
        $batch->add();
        $batch->setId($id);

        $buffer = $batch->getBuffer();
        $unpacked = BinaryHelper::unpackUint128($buffer);

        $this->assertTrue($id->equals($unpacked));
    }
}
