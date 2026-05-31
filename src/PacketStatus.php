<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle packet status codes.
 *
 * Maps to TB_PACKET_STATUS enum in tb_client.h.
 */
enum PacketStatus: int
{
    case OK = 0;
    case TOO_MUCH_DATA = 1;
    case INVALID_OPERATION = 2;
    case INVALID_DATA_SIZE = 3;
    case ZERO_ADDRESS = 4;
    case ZERO_CLUSTER_ID = 5;
    case CONCURRENCY_MAX_EXCEEDED = 6;
}
