<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Result of creating a single account.
 *
 * Contains the account ID and the status of the create operation.
 */
final readonly class CreateAccountResult
{
    /**
     * TODO: implement
     */
    public function __construct(
        private Uint128 $id,
        private CreateAccountStatus $status = CreateAccountStatus::CREATED,
    ) {
    }

    public function getId(): Uint128
    {
        return $this->id;
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
