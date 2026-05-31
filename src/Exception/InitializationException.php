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
     *
     * @param int $status One of the InitStatus::* constants.
     */
    public static function fromStatus(int $status): self
    {
        $messages = [
            InitStatus::SUCCESS => 'Success',
            InitStatus::UNEXPECTED => 'Unexpected error during initialization',
            InitStatus::OUT_OF_MEMORY => 'Out of memory during initialization',
            InitStatus::SYSTEM_RESOURCES => 'Insufficient system resources',
            InitStatus::NETWORK_SUBSYSTEM => 'Network subsystem error',
        ];

        return new self(
            \sprintf(
                'TigerBeetle client initialization failed: %s',
                $messages[$status] ?? \sprintf('Unknown status %d', $status),
            ),
        );
    }
}
