<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when trying to use a closed client.
 */
final class ClientClosedException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function create(): self
    {
        return new self('Client has been closed');
    }
}
