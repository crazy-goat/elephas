<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

use CrazyGoat\Elephas\InitStatus;

/**
 * Exception thrown when TigerBeetle client initialization fails.
 */
final class InitializationException extends \RuntimeException implements ElephasExceptionInterface
{
    /**
     * Create exception for initialization failure.
     */
    public static function create(string $message = ''): self
    {
        return new self(
            $message ?: 'Failed to initialize TigerBeetle client',
        );
    }

    /**
     * Create exception from initialization status code.
     */
    public static function fromStatus(InitStatus $status): self
    {
        $messages = [
            InitStatus::SUCCESS->value => 'Success',
            InitStatus::UNEXPECTED->value => 'Unexpected error during initialization',
            InitStatus::OUT_OF_MEMORY->value => 'Out of memory during initialization',
            InitStatus::INVALID_ADDRESS->value => 'Invalid cluster address',
            InitStatus::SYSTEM_RESOURCES->value => 'Insufficient system resources',
            InitStatus::NETWORK_SUBSYSTEM->value => 'Network subsystem error',
        ];

        return new self(
            \sprintf(
                'TigerBeetle client initialization failed: %s',
                $messages[$status->value] ?? \sprintf('Unknown status %d', $status->value),
            ),
        );
    }
}
