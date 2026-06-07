<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\BackendFactory;
use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackendFactory::class)]
final class BackendFactoryTest extends TestCase
{
    public function testIsFinal(): void
    {
        $reflection = new \ReflectionClass(BackendFactory::class);

        $this->assertTrue($reflection->isFinal());
    }

    public function testIsFfiAvailableReturnsFalseWithoutFfi(): void
    {
        if (\extension_loaded('ffi')) {
            $this->markTestSkipped('FFI is loaded, cannot test false scenario');
        }

        $this->assertFalse(BackendFactory::isFfiAvailable());
    }

    public function testIsExtensionAvailableReturnsFalse(): void
    {
        $this->assertFalse(BackendFactory::isExtensionAvailable());
    }

    public function testCreateThrowsWhenNoBackend(): void
    {
        if (BackendFactory::isFfiAvailable()) {
            $this->markTestSkipped('FFI is available, cannot test no-backend scenario');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No backend available');

        BackendFactory::create(Uint128::zero(), ['127.0.0.1:3000']);
    }

    public function testCreateReturnsBackendInterface(): void
    {
        if (!BackendFactory::isFfiAvailable()) {
            $this->markTestSkipped('FFI not available, cannot test backend creation');
        }

        // Creating a backend requires a running TigerBeetle instance.
        // If TigerBeetle is not available, skip the test gracefully.
        try {
            $backend = BackendFactory::create(Uint128::zero(), ['127.0.0.1:3000']);
        } catch (\RuntimeException $e) {
            if (\str_contains($e->getMessage(), 'No backend available')) {
                $this->markTestSkipped(
                    'TigerBeetle is not running, cannot test backend creation',
                );
            }
            throw $e;
        }

        $this->assertInstanceOf(BackendInterface::class, $backend);

        $backend->close();
    }
}
