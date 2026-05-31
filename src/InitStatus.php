<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle initialization status codes.
 *
 * Maps to TB_INIT_STATUS enum in tb_client.h.
 */
enum InitStatus: int
{
    case SUCCESS = 0;
    case UNEXPECTED = 1;
    case OUT_OF_MEMORY = 2;
    case INVALID_ADDRESS = 3;
    case SYSTEM_RESOURCES = 4;
    case NETWORK_SUBSYSTEM = 5;
}
