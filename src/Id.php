<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * ULID generator for TigerBeetle account and transfer IDs.
 *
 * Generates monotonic 128-bit IDs with 48-bit timestamp
 * and 80-bit random component.
 */
final class Id
{
    private static int $lastTimestamp = 0;

    private static int $lastRandom = 0;

    public static function getLastTimestamp(): int
    {
        return self::$lastTimestamp;
    }

    public static function getLastRandom(): int
    {
        return self::$lastRandom;
    }

    /**
     * Generate a new unique ID.
     *
     * The ID consists of:
     * - 48-bit timestamp (milliseconds since Unix epoch)
     * - 80-bit random component
     *
     * Monotonicity is guaranteed: if called multiple times within
     * the same millisecond, the random component is incremented.
     *
     * TODO: implement
     */
    public static function generate(): Uint128
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
