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
    public const LINKED = 1 << 0;

    public const PENDING = 1 << 1;

    public const POST_PENDING = 1 << 2;

    public const VOID_PENDING = 1 << 3;

    public const IMPORTED = 1 << 4;
}
