<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use PHPUnit\Framework\TestCase;

class CITest extends TestCase
{
    public function testTigerBeetleAddressEnvIsSet(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS');
        $this->assertNotFalse($address, 'TIGERBEETLE_ADDRESS env var must be set');
        $this->assertNotEmpty($address, 'TIGERBEETLE_ADDRESS env var must not be empty');
    }

    public function testTigerBeetleIsReachable(): void
    {
        $address = getenv('TIGERBEETLE_ADDRESS') ?: 'localhost:3000';

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
