<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Result of creating a single transfer.
 *
 * Contains the transfer ID and the status of the create operation.
 */
final readonly class CreateTransferResult
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $id,
        private CreateTransferStatus $status = CreateTransferStatus::CREATED,
    ) {
    }

    public function getId(): Uint128
    {
        return $this->id;
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
