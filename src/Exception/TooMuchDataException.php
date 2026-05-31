<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when batch contains too much data.
 */
final class TooMuchDataException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function create(int $maxSize, int $actualSize): self
    {
        return new self(
            \sprintf('Data size %d exceeds maximum allowed %d bytes', $actualSize, $maxSize),
        );
    }
}
