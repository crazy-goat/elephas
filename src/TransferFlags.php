<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle transfer flags.
 *
 * Flags can be combined with bitwise OR.
 * Maps to TB_TRANSFER_FLAGS in tb_client.h.
 */
final class TransferFlags
{
    public const NONE = 0;

    public const LINKED = 1 << 0;

    public const PENDING = 1 << 1;

    public const POST_PENDING_TRANSFER = 1 << 2;

    public const VOID_PENDING_TRANSFER = 1 << 3;

    public const BALANCING_DEBIT = 1 << 4;

    public const BALANCING_CREDIT = 1 << 5;

    public const CLOSING_DEBIT = 1 << 6;

    public const CLOSING_CREDIT = 1 << 7;

    public const IMPORTED = 1 << 8;

    public const ZERO_VALUE_TRANSFERS = 1 << 9;

    public static function combine(int ...$flags): int
    {
        return array_reduce($flags, fn(int $carry, int $flag): int => $carry | $flag, 0);
    }
}
