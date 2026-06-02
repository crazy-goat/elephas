<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when a batch getter or setter is called while the cursor
 * is outside the populated range (e.g. before the first add() or after
 * navigation past the last record).
 */
final class InvalidBatchCursorException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function atPosition(string $batchClass, int $position, int $length, string $action): self
    {
        return new self(\sprintf(
            'Cannot %s on %s: cursor position %d is outside the populated range [0, %d). Call add() or rewind() before accessing batch elements.',
            $action,
            $batchClass,
            $position,
            $length,
        ));
    }
}
