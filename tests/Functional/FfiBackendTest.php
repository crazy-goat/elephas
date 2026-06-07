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

        // Closing the backend must not throw.
        $backend->close();

        // After close, submitting any operation must throw ClientClosedException.
        $this->expectException(\CrazyGoat\Elephas\Exception\ClientClosedException::class);
        $backend->submit(\CrazyGoat\Elephas\Operation::PULSE, '');
    }

    public function testSubmitCreateAccounts(): void
    {
        $backend = $this->createBackend();
        if (!$backend instanceof \CrazyGoat\Elephas\Backend\FfiBackend) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $result = $backend->submit(Operation::CREATE_ACCOUNTS, \str_repeat("\0", 128));

        // 128 zero bytes = 1 account with all-defaults. TigerBeetle returns
        // one 16-byte CreateAccountResult. We don't assert the status here
        // (it's likely LINKED_EVENT_FAILED due to zero ID/ledger/code), but
        // the raw result size confirms the backend processed the request.
        $this->assertSame(16, \strlen($result), 'CreateAccounts result must be 16 bytes for 1 account');

        $backend->close();

        // After close, submitting must throw.
        $this->expectException(\CrazyGoat\Elephas\Exception\ClientClosedException::class);
        $backend->submit(Operation::PULSE, '');
    }

    public function testMultipleSequentialSubmits(): void
    {
        $backend = $this->createBackend();
        if (!$backend instanceof \CrazyGoat\Elephas\Backend\FfiBackend) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        for ($i = 0; $i < 10; $i++) {
            $result = $backend->submit(Operation::PULSE, '');
            $this->assertSame('', $result, 'PULSE operation must return empty response');
        }

        $backend->close();

        // After close, submitting must throw.
        $this->expectException(\CrazyGoat\Elephas\Exception\ClientClosedException::class);
        $backend->submit(Operation::PULSE, '');
    }
}
