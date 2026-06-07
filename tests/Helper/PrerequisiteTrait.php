<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Helper;

trait PrerequisiteTrait
{
    private function isCi(): bool
    {
        return \getenv('CI') !== false;
    }

    private function failOrMarkTestSkipped(string $message): never
    {
        if ($this->isCi()) {
            self::fail($message);
        }

        self::markTestSkipped($message);
    }

    private function getTigerBeetleAddress(): ?string
    {
        $address = \getenv('TIGERBEETLE_ADDRESS');
        if (!\is_string($address) || $address === '') {
            return null;
        }

        return $address;
    }

    private function isFfiBackendAvailable(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        try {
            new \CrazyGoat\Elephas\Backend\FfiBackend(
                \CrazyGoat\Elephas\Uint128\Uint128::zero(),
                ['127.0.0.1:1'],
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
