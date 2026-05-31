<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Factory for creating TigerBeetle backends.
 *
 * Auto-detects the best available backend implementation.
 */
class BackendFactory
{
    /**
     * Create a new backend instance.
     *
     * Detection order:
     * 1. FFI extension + tb_client library → FfiBackend
     * 2. Native PHP implementation (fallback)
     *
     * @param array<string> $replicaAddresses
     *
     * @throws \RuntimeException if no backend is available
     *
     * TODO: implement
     */
    public static function create(
        Uint128 $clusterId,
        array $replicaAddresses,
    ): BackendInterface {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
