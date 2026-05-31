<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

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
}
