<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\BackendFactory;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class BackendFactoryTest extends TestCase
{
    private function getAddress(): ?string
    {
        $address = \getenv('TIGERBEETLE_ADDRESS');

        return ($address === false || $address === '') ? null : $address;
    }

    private function isFfiAvailable(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        try {
            new \CrazyGoat\Elephas\Backend\NativeClient();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function testIsFfiAvailableReturnsTrue(): void
    {
        if (!$this->isFfiAvailable()) {
            $this->markTestSkipped('FFI native client library not available');
        }

        $this->assertTrue(BackendFactory::isFfiAvailable());
    }

    public function testCreateConnectsToTigerBeetle(): void
    {
        $address = $this->getAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiAvailable()) {
            $this->markTestSkipped('FFI native client library not available');
        }

        $backend = BackendFactory::create(Uint128::zero(), [$address]);

        $this->assertInstanceOf(FfiBackend::class, $backend);

        $backend->submit(Operation::PULSE, '');
        $backend->close();

        $this->assertInstanceOf(FfiBackend::class, $backend);
    }
}
