<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Uint128\Uint128;

final class BackendFactory
{
    /**
     * @param array<string> $replicaAddresses
     * @param float|null    $timeoutSeconds  forwarded to the backend; see {@see NativeClient} for the default
     * @param string|null   $libPath         explicit path to the tb_client shared library, or null for
     *                                       auto-detect (only project-local paths under resources/lib/
     *                                       are searched).  **Security**: always specify an explicit,
     *                                       trusted library path in production to avoid loading an
     *                                       untrusted native library via FFI.
     */
    public static function create(
        Uint128 $clusterId,
        array $replicaAddresses,
        ?float $timeoutSeconds = null,
        ?string $libPath = null,
    ): BackendInterface {
        if (\extension_loaded('ffi')) {
            try {
                return new FfiBackend($clusterId, $replicaAddresses, null, $libPath, $timeoutSeconds);
            } catch (\Throwable) {
            }
        }

        throw new \RuntimeException(
            'No backend available. FFI extension must be loaded and tb_client library must be accessible.',
        );
    }

    public static function isFfiAvailable(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        try {
            new NativeClient();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isExtensionAvailable(): bool
    {
        return false;
    }
}
