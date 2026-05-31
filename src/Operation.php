<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle operation codes.
 *
 * Maps to TB_OPERATION enum in tb_client.h.
 */
enum Operation: int
{
    case PULSE = 128;
    case CREATE_ACCOUNTS = 146;
    case CREATE_TRANSFERS = 147;
    case LOOKUP_ACCOUNTS = 148;
    case LOOKUP_TRANSFERS = 149;
    case GET_ACCOUNT_TRANSFERS = 150;
    case GET_ACCOUNT_BALANCES = 151;
    case QUERY_ACCOUNTS = 152;
    case QUERY_TRANSFERS = 153;
}
