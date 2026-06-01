<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class FfiBackendTest extends TestCase
{
    private function createBackend(): ?FfiBackend
    {
        $address = \getenv('TIGERBEETLE_ADDRESS');
        if ($address === false || $address === '') {
            return null;
        }

        if (!\extension_loaded('ffi')) {
            return null;
        }

        try {
            return new FfiBackend(
                Uint128::zero(),
                [$address],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function testInitAndClose(): void
    {
        $backend = $this->createBackend();
        if (!$backend instanceof \CrazyGoat\Elephas\Backend\FfiBackend) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $backend->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testSubmitCreateAccounts(): void
    {
        $backend = $this->createBackend();
        if (!$backend instanceof \CrazyGoat\Elephas\Backend\FfiBackend) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $result = $backend->submit(Operation::CREATE_ACCOUNTS, \str_repeat("\0", 128));
        $backend->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertIsString($result);
    }

    public function testMultipleSequentialSubmits(): void
    {
        $backend = $this->createBackend();
        if (!$backend instanceof \CrazyGoat\Elephas\Backend\FfiBackend) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        for ($i = 0; $i < 10; $i++) {
            $result = $backend->submit(Operation::PULSE, '');
            /** @phpstan-ignore method.alreadyNarrowedType */
            $this->assertIsString($result);
        }

        $backend->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }
}
