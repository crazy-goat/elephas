<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * Result of creating a single transfer.
 *
 * Contains the TigerBeetle-assigned timestamp and the status of the create
 * operation.  Each result corresponds positionally to one transfer in the
 * request batch.
 */
final readonly class CreateTransferResult
{
    public function __construct(
        private int $timestamp,
        private CreateTransferStatus $status = CreateTransferStatus::CREATED,
    ) {
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getStatus(): CreateTransferStatus
    {
        return $this->status;
    }

    public function isCreated(): bool
    {
        return $this->status === CreateTransferStatus::CREATED;
    }
}
