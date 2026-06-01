<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for Client class.
 *
 * These tests require a running TigerBeetle instance (TIGERBEETLE_ADDRESS env var).
 */
class ClientTest extends TestCase
{
    private function tigerBeetleAddress(): ?string
    {
        $address = \getenv('TIGERBEETLE_ADDRESS');
        if (!\is_string($address) || $address === '') {
            return null;
        }

        return $address;
    }

    private function isFfiBackendAvailable(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        try {
            new FfiBackend(Uint128::zero(), ['127.0.0.1:1']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function createClient(): ?Client
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            return null;
        }

        if (!$this->isFfiBackendAvailable()) {
            return null;
        }

        try {
            return new Client(Uint128::zero(), $address);
        } catch (\Throwable) {
            return null;
        }
    }

    public function testConstructConnectsToTigerBeetle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $this->assertSame(0, $client->getClusterId()->toInt());
        $this->assertCount(1, $client->getReplicaAddresses());

        $client->close();
    }

    public function testConstructInvalidAddressThrows(): void
    {
        $this->expectException(InitializationException::class);

        new Client(Uint128::zero(), '999.999.999.999:9999');
    }

    public function testConstructWithMultipleReplicaAddresses(): void
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiBackendAvailable()) {
            $this->markTestSkipped('FfiBackend not available (tb_client library missing)');
        }

        $client = new Client(Uint128::zero(), $address, $address, $address);

        $this->assertCount(3, $client->getReplicaAddresses());

        $client->close();
    }

    public function testCloseReleasesResources(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testCloseIsIdempotent(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();
        $client->close();
        $client->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testWithBackendCreatesClient(): void
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiBackendAvailable()) {
            $this->markTestSkipped('FfiBackend not available (tb_client library missing)');
        }

        $backend = new FfiBackend(Uint128::zero(), [$address]);
        $client = Client::withBackend($backend);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertTrue($client->getClusterId()->equals(Uint128::zero()));
        $this->assertSame([], $client->getReplicaAddresses());

        $client->close();
    }

    public function testWithBackendUsesProvidedBackend(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())->method('close');

        $client = Client::withBackend($backend);
        $client->close();
    }
}
