<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when a request to TigerBeetle fails.
 */
final class RequestException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function create(int $status, string $message = ''): self
    {
        return new self(
            $message ?: \sprintf('Request failed with status %d', $status),
        );
    }
}
