<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use PHPUnit\Framework\TestCase;

class CITest extends TestCase
{
    private function isCi(): bool
    {
        return getenv('CI') !== false || getenv('GITHUB_ACTIONS') !== false;
    }

    public function testTigerBeetleAddressEnvIsSet(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS');
        if ($address === false) {
            if ($this->isCi()) {
                $this->fail('TIGERBEETLE_ADDRESS env var is not set (required in CI)');
            }
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set (not in CI)');
        }
        $this->assertNotEmpty($address, 'TIGERBEETLE_ADDRESS env var must not be empty');
    }

    public function testTigerBeetleIsReachable(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS');
        if ($address === false) {
            if ($this->isCi()) {
                $this->fail('TIGERBEETLE_ADDRESS env var is not set (required in CI)');
            }
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set (not in CI)');
        }

        $parts = explode(':', $address);
        $host = $parts[0];
        $port = (int) ($parts[1] ?? 3000);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($socket === false) {
            if ($this->isCi()) {
                $this->fail(
                    sprintf('Cannot connect to TigerBeetle at %s:%d (%s) – required in CI', $host, $port, $errstr),
                );
            }
            $this->markTestSkipped(
                sprintf('Cannot connect to TigerBeetle at %s:%d (%s)', $host, $port, $errstr),
            );
        }

        $this->assertNotFalse($socket, sprintf('TigerBeetle must be reachable at %s:%d', $host, $port));
        fclose($socket);
    }
}
