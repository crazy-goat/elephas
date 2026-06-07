<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\BackendFactory;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\Test\Helper\PrerequisiteTrait;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class BackendFactoryTest extends TestCase
{
    use PrerequisiteTrait;

    public function testIsFfiAvailableReturnsTrue(): void
    {
        if (!$this->isFfiBackendAvailable()) {
            $this->failOrMarkTestSkipped('FFI native client library not available');
        }

        $this->assertTrue(BackendFactory::isFfiAvailable());
    }

    public function testCreateConnectsToTigerBeetle(): void
    {
        $address = $this->getTigerBeetleAddress();
        if ($address === null) {
            $this->failOrMarkTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiBackendAvailable()) {
            $this->failOrMarkTestSkipped('FFI native client library not available');
        }

        $backend = BackendFactory::create(Uint128::zero(), [$address]);

        $this->assertInstanceOf(FfiBackend::class, $backend);

        $backend->submit(Operation::PULSE, '');
        $backend->close();

        $this->assertInstanceOf(FfiBackend::class, $backend);
    }
}
