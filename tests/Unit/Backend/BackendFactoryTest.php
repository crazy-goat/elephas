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

    public function testCreateWithLibPathThrowsWhenLibraryNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No backend available');

        BackendFactory::create(
            Uint128::zero(),
            ['127.0.0.1:3000'],
            null,
            '/nonexistent/path/libtb_client.so',
        );
    }

    public function testCreateAcceptsLibPathParameter(): void
    {
        if (!BackendFactory::isFfiAvailable()) {
            $this->markTestSkipped('FFI not available, cannot test backend creation');
        }

        // Using a real but wrong library path should result in a
        // different error than "No backend available" — the backend
        // factory will try to use FFI with the given path and fail
        // with InitializationException instead.
        $libcPath = \PHP_OS_FAMILY === 'Linux' ? '/lib/x86_64-linux-gnu/libc.so.6' : '/usr/lib/libSystem.dylib';

        if (!\file_exists($libcPath)) {
            $this->markTestSkipped("Test library not found at {$libcPath}");
        }

        try {
            BackendFactory::create(
                Uint128::zero(),
                ['127.0.0.1:3000'],
                null,
                $libcPath,
            );
            $this->fail('Expected an exception');
        } catch (\RuntimeException $e) {
            // Should NOT say "No backend available" — that means the
            // libPath was ignored and FFI detection was skipped
            $this->assertStringNotContainsString('No backend available', $e->getMessage());
        } catch (\Throwable) {
            // Any throwable is fine as long as it's not the generic
            // "No backend available" message
        }
    }
}
