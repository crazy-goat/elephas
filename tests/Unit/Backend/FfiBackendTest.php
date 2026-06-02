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

        $this->backend = new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $this->nativeClient,
        );
    }

    public function testConstructorCallsNativeClientInit(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->expects($this->once())
            ->method('init')
            ->with($this->isType('string'), $this->isType('array'));

        new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $nativeClient,
        );
    }

    public function testConstructorAcceptsLibPathParameter(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->expects($this->once())
            ->method('init');

        new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $nativeClient,
            '/custom/path/libtb_client.so',
        );
    }

    public function testConstructorWithMultipleAddresses(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->expects($this->once())
            ->method('init')
            ->with($this->isType('string'), ['127.0.0.1:3001', '127.0.0.1:3002', '127.0.0.1:3003']);

        new FfiBackend(
            Uint128::fromInt(1),
            ['127.0.0.1:3001', '127.0.0.1:3002', '127.0.0.1:3003'],
            $nativeClient,
        );
    }

    public function testConstructorWithNonZeroClusterId(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $clusterId = Uint128::fromInt(42);
        $nativeClient
            ->expects($this->once())
            ->method('init')
            ->with($clusterId->toBytes(), $this->isType('array'));

        new FfiBackend(
            $clusterId,
            ['127.0.0.1:3000'],
            $nativeClient,
        );
    }

    public function testConstructorInitExceptionPropagates(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->expects($this->once())
            ->method('init')
            ->willThrowException(new \CrazyGoat\Elephas\Exception\InitializationException('Init failed'));

        $this->expectException(\CrazyGoat\Elephas\Exception\InitializationException::class);
        $this->expectExceptionMessage('Init failed');

        new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $nativeClient,
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

    public function testCloseCallsDeinit(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->method('init');
        $nativeClient
            ->expects($this->once())
            ->method('deinit');

        $backend = new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $nativeClient,
        );

        $backend->close();
    }

    public function testMultipleCloseOnlyCallsDeinitOnce(): void
    {
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->method('init');
        $nativeClient
            ->expects($this->once())
            ->method('deinit');

        $backend = new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            $nativeClient,
        );

        $backend->close();
        $backend->close();
        $backend->close();
    }

    public function testGetClusterId(): void
    {
        $this->assertEquals(Uint128::zero(), $this->backend->getClusterId());
    }

    public function testGetClusterIdWithCustomValue(): void
    {
        $clusterId = Uint128::fromString('123456789');
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->method('init');

        $backend = new FfiBackend(
            $clusterId,
            ['127.0.0.1:3000'],
            $nativeClient,
        );

        $this->assertTrue($clusterId->equals($backend->getClusterId()));
    }

    public function testGetReplicaAddresses(): void
    {
        $this->assertSame(['127.0.0.1:3000'], $this->backend->getReplicaAddresses());
    }

    public function testGetReplicaAddressesWithMultiple(): void
    {
        $addresses = ['127.0.0.1:3001', '127.0.0.1:3002'];
        $nativeClient = $this->createMock(NativeClient::class);
        $nativeClient
            ->method('init');

        $backend = new FfiBackend(
            Uint128::zero(),
            $addresses,
            $nativeClient,
        );

        $this->assertSame($addresses, $backend->getReplicaAddresses());
    }

    public function testConstructorRejectsZeroTimeoutWhenCreatingNativeClient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout must be positive');

        new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            null,
            '/nonexistent/libtb_client.so',
            0.0,
        );
    }

    public function testConstructorRejectsNegativeTimeoutWhenCreatingNativeClient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout must be positive');

        new FfiBackend(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            null,
            '/nonexistent/libtb_client.so',
            -2.0,
        );
    }
}
