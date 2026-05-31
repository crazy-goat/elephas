<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Internal;

use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;

/**
 * Packet wrapper for tb_client callback with synchronization.
 *
 * Manages the lifecycle of a single request/response cycle.
 */
class Packet
{
    private int $status = PacketStatus::OK;

    private string $data = '';

    private bool $completed = false;

    /**
     * TODO: implement
     */
    public function __construct(
        private readonly Operation $operation,
        private readonly string $payload,
    ) {
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function complete(): void
    {
        $this->completed = true;
    }
}
