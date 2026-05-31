<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle account filter flags.
 *
 * Flags can be combined with bitwise OR.
 * Maps to TB_ACCOUNT_FILTER_FLAGS in tb_client.h.
 */
final class AccountFilterFlags
{
    public const DEBITS = 1 << 0;

    public const CREDITS = 1 << 1;

    public const REVERSED = 1 << 2;
}
