<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * Result of creating a single account.
 *
 * Contains the TigerBeetle-assigned timestamp and the status of the create
 * operation.  Each result corresponds positionally to one account in the
 * request batch.
 */
final readonly class CreateAccountResult
{
    public function __construct(
        private int $timestamp,
        private CreateAccountStatus $status = CreateAccountStatus::CREATED,
    ) {
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getStatus(): CreateAccountStatus
    {
        return $this->status;
    }

    public function isCreated(): bool
    {
        return $this->status === CreateAccountStatus::CREATED;
    }
}
