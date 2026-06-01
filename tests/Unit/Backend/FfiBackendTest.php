<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Backend\NativeClient;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FfiBackendTest extends TestCase
{
    private MockObject $nativeClient;

    private FfiBackend $backend;

    protected function setUp(): void
    {
        $this->nativeClient = $this->createMock(NativeClient::class);
        $this->nativeClient
            ->expects($this->once())
            ->method('init')
            ->with($this->isType('string'), $this->isType('array'));

        $this->backend = new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $this->nativeClient,
        );
    }

    public function testSubmitReturnsData(): void
    {
        $this->nativeClient
            ->expects($this->once())
            ->method('submit')
            ->with(Operation::CREATE_ACCOUNTS, 'request-data')
            ->willReturn('response-data');

        $result = $this->backend->submit(Operation::CREATE_ACCOUNTS, 'request-data');

        $this->assertSame('response-data', $result);
    }

    public function testSubmitForwardsAllOperations(): void
    {
        $operations = [
            Operation::CREATE_ACCOUNTS,
            Operation::CREATE_TRANSFERS,
            Operation::LOOKUP_ACCOUNTS,
            Operation::LOOKUP_TRANSFERS,
            Operation::GET_ACCOUNT_TRANSFERS,
            Operation::GET_ACCOUNT_BALANCES,
        ];

        $callCount = 0;

        $this->nativeClient
            ->expects($this->exactly(\count($operations)))
            ->method('submit')
            ->willReturnCallback(function () use (&$callCount): string {
                $callCount++;

                return 'response';
            });

        foreach ($operations as $op) {
            $this->backend->submit($op, 'data');
        }

        $this->assertSame(\count($operations), $callCount);
    }

    public function testSubmitAfterCloseThrows(): void
    {
        $this->nativeClient
            ->expects($this->once())
            ->method('deinit');

        $this->backend->close();

        $this->expectException(\CrazyGoat\Elephas\Exception\ClientClosedException::class);

        $this->backend->submit(Operation::CREATE_ACCOUNTS, '');
    }

    public function testCloseIsIdempotent(): void
    {
        $this->nativeClient
            ->expects($this->once())
            ->method('deinit');

        $this->backend->close();
        $this->backend->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testGetClusterId(): void
    {
        $this->assertEquals(Uint128::zero(), $this->backend->getClusterId());
    }

    public function testGetReplicaAddresses(): void
    {
        $this->assertSame(['127.0.0.1:3000'], $this->backend->getReplicaAddresses());
    }
}
