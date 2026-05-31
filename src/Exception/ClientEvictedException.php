<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when client session is evicted.
 */
final class ClientEvictedException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function create(): self
    {
        return new self('Client session has been evicted');
    }
}
