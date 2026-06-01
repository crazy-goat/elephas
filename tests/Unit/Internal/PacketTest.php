<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Internal;

use CrazyGoat\Elephas\Internal\Packet;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;
use PHPUnit\Framework\TestCase;

class PacketTest extends TestCase
{
    public function testConstructorSetsOperationAndPayload(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, 'data');

        $this->assertSame(Operation::CREATE_ACCOUNTS, $packet->getOperation());
        $this->assertSame('data', $packet->getPayload());
    }

    public function testDefaultStatusIsOk(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $this->assertSame(PacketStatus::OK, $packet->getStatus());
    }

    public function testDefaultDataIsEmpty(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $this->assertSame('', $packet->getData());
    }

    public function testDefaultNotCompleted(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $this->assertFalse($packet->isCompleted());
    }

    public function testOnCompleteSetsDataAndStatus(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $packet->onComplete(PacketStatus::OK, 'response');

        $this->assertTrue($packet->isCompleted());
        $this->assertSame(PacketStatus::OK, $packet->getStatus());
        $this->assertSame('response', $packet->getData());
    }

    public function testOnCompleteWithErrorStatus(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $packet->onComplete(PacketStatus::TOO_MUCH_DATA, '');

        $this->assertTrue($packet->isCompleted());
        $this->assertSame(PacketStatus::TOO_MUCH_DATA, $packet->getStatus());
    }

    public function testWaitCompletesImmediatelyWhenAlreadyDone(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');
        $packet->onComplete(PacketStatus::OK, 'data');

        $packet->wait();

        $this->assertTrue($packet->isCompleted());
    }

    public function testWaitReturnsWhenOnCompleteCalledBeforehand(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, 'payload');
        $packet->onComplete(PacketStatus::OK, 'response');

        $start = \microtime(true);
        $packet->wait(1_000_000);
        $elapsed = (\microtime(true) - $start) * 1_000_000;

        $this->assertTrue($packet->isCompleted());
        $this->assertSame('response', $packet->getData());
        $this->assertLessThan(10_000, $elapsed);
    }

    public function testWaitThrowsOnTimeout(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Packet wait timeout');

        $packet->wait(100);
    }

    public function testSetStatusAndSetData(): void
    {
        $packet = new Packet(Operation::LOOKUP_ACCOUNTS, 'ids');

        $packet->setStatus(PacketStatus::INVALID_OPERATION);
        $packet->setData('error');

        $this->assertSame(PacketStatus::INVALID_OPERATION, $packet->getStatus());
        $this->assertSame('error', $packet->getData());
    }

    public function testComplete(): void
    {
        $packet = new Packet(Operation::CREATE_ACCOUNTS, '');

        $this->assertFalse($packet->isCompleted());

        $packet->complete();

        $this->assertTrue($packet->isCompleted());
    }
}
