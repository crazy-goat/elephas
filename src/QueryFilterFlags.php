<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle query filter flags.
 *
 * Flags can be combined with bitwise OR.
 * Maps to TB_QUERY_FILTER_FLAGS in tb_client.h.
 */
final class QueryFilterFlags
{
    public const NONE = 0;

    public const REVERSED = 1 << 0;

    public static function combine(int ...$flags): int
    {
        return array_reduce($flags, fn(int $carry, int $flag): int => $carry | $flag, 0);
    }
}
