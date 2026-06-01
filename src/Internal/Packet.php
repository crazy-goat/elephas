<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Internal;

use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;

final class Packet
{
    private bool $completed = false;

    private ?string $responseData = null;

    private ?PacketStatus $status = null;

    public function __construct(
        private readonly Operation $operation,
        private readonly string $payload,
    ) {
    }

    public function onComplete(PacketStatus $status, ?string $data): void
    {
        $this->status = $status;
        $this->responseData = $data;
        $this->completed = true;
    }

    public function wait(int $timeout = 0): void
    {
        $elapsed = 0;

        while (!$this->completed) {
            if ($timeout > 0 && $elapsed >= $timeout) {
                throw new \RuntimeException('Packet wait timeout');
            }

            usleep(100);
            $elapsed += 100;
        }
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function getStatus(): PacketStatus
    {
        if (!$this->status instanceof \CrazyGoat\Elephas\PacketStatus) {
            throw new \RuntimeException('Packet status not available before completion');
        }

        return $this->status;
    }

    public function getData(): ?string
    {
        return $this->responseData;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
