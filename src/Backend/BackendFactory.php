<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Uint128\Uint128;

class BackendFactory
{
    /**
     * @param array<string> $replicaAddresses
     */
    public static function create(
        Uint128 $clusterId,
        array $replicaAddresses,
    ): BackendInterface {
        if (\extension_loaded('ffi')) {
            try {
                return new FfiBackend($clusterId, $replicaAddresses);
            } catch (\Throwable) {
            }
        }

        throw new \RuntimeException(
            'No backend available. FFI extension must be loaded and tb_client library must be accessible.',
        );
    }
}
