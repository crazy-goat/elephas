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

    public function testIsFfiAvailableReturnsTrue(): void
    {
        if (!\extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension not loaded');
        }

        $this->assertTrue(BackendFactory::isFfiAvailable());
    }

    public function testCreateConnectsToTigerBeetle(): void
    {
        $address = $this->getAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        $backend = BackendFactory::create(Uint128::zero(), [$address]);

        $this->assertInstanceOf(FfiBackend::class, $backend);

        $backend->submit(Operation::PULSE, '');
        $backend->close();

        $this->assertInstanceOf(FfiBackend::class, $backend);
    }
}
