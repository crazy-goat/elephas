<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle account flags.
 *
 * Flags can be combined with bitwise OR.
 * Maps to TB_ACCOUNT_FLAGS in tb_client.h.
 */
final class AccountFlags
{
    public const LINKED = 1 << 0;

    public const DEBITS_MUST_NOT_EXCEED_CREDITS = 1 << 1;

    public const CREDITS_MUST_NOT_EXCEED_DEBITS = 1 << 2;

    public const HISTORY = 1 << 3;

    public const IMPORTED = 1 << 4;
}
