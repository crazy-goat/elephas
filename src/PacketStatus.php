<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle packet status codes.
 *
 * Maps to TB_PACKET_STATUS enum in tb_client.h.
 */
final class PacketStatus
{
    public const OK = 0;

    public const TOO_MUCH_DATA = 1;

    public const INVALID_OPERATION = 2;

    public const INVALID_DATA_SIZE = 3;

    public const CONFLICT = 4;

    public const EXCEEDED_HOME_SIZE = 5;

    public const CLIENT_RELEASE_TOO_LOW = 6;

    public const CLIENT_RELEASE_TOO_HIGH = 7;

    public const INVALID_CLIENT_RB_CONTEXT = 8;

    public const CLIENT_EVICTED = 9;

    public const TOO_MANY_BATCHES = 10;

    public const CLIENT_SHUTTING_DOWN = 11;

    public const CLIENT_MEMORY = 12;

    public const CLIENT_TABLE = 13;

    public const CLIENT_STREAMS = 14;
}
