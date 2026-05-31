<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

final class IntegerOverflowException extends \RuntimeException implements ElephasExceptionInterface
{
    public static function forIntOverflow(string $value): self
    {
        return new self(
            \sprintf('Value %s exceeds PHP_INT_MAX and cannot be represented as a signed 64-bit integer', $value),
        );
    }

    public static function forValue(string $value): self
    {
        return new self(
            \sprintf('Value %s exceeds maximum 128-bit unsigned integer (2^128-1)', $value),
        );
    }
}
