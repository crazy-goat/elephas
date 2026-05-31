<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle client status codes.
 *
 * Maps to TB_CLIENT_STATUS enum in tb_client.h.
 */
enum ClientStatus: int
{
    case OK = 0;
    case INVALID = 1;
    case TOO_MUCH_DATA = 2;
    case CONCURRENCY_MAX_EXCEEDED = 3;
}
