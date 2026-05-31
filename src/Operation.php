<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle operation codes.
 *
 * Maps to TB_OPERATION enum in tb_client.h.
 */
final class Operation
{
    public const PULSE = 128;

    public const CREATE_ACCOUNTS = 146;

    public const CREATE_TRANSFERS = 147;

    public const LOOKUP_ACCOUNTS = 148;

    public const LOOKUP_TRANSFERS = 149;

    public const GET_ACCOUNT_TRANSFERS = 150;

    public const GET_ACCOUNT_BALANCES = 151;

    public const QUERY_ACCOUNTS = 152;

    public const QUERY_TRANSFERS = 153;
}
