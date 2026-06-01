<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use PHPUnit\Framework\TestCase;

class CITest extends TestCase
{
    public function testTigerBeetleAddressEnvIsSet(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS');
        if ($address === false) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set (not in CI)');
        }
        $this->assertNotEmpty($address, 'TIGERBEETLE_ADDRESS env var must not be empty');
    }

    public function testTigerBeetleIsReachable(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS');
        if ($address === false) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set (not in CI)');
        }

        $parts = explode(':', $address);
        $host = $parts[0];
        $port = (int) ($parts[1] ?? 3000);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        $this->assertNotFalse(
            $socket,
            sprintf('Cannot connect to TigerBeetle at %s:%d (%s)', $host, $port, $errstr),
        );

        fclose($socket);
    }
}
