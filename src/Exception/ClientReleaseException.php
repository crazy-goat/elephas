<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when client version is incompatible.
 */
final class ClientReleaseException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function create(string $message = ''): self
    {
        return new self(
            $message ?: 'Client release is incompatible with TigerBeetle server',
        );
    }
}
