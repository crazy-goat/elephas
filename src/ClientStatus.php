<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle client status codes.
 *
 * Maps to TB_CLIENT_STATUS enum in tb_client.h.
 */
final class ClientStatus
{
    public const OK = 0;

    public const INVALID = 1;
}
