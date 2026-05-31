<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle initialization status codes.
 *
 * Maps to TB_INIT_STATUS enum in tb_client.h.
 */
final class InitStatus
{
    public const SUCCESS = 0;

    public const UNEXPECTED = 1;

    public const OUT_OF_MEMORY = 2;

    public const SYSTEM_RESOURCES = 3;

    public const NETWORK_SUBSYSTEM = 4;
}
