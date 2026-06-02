<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Internal;

use CrazyGoat\Elephas\Exception\IntegerOverflowException;

final class BinaryRange
{
    public const UINT16_MAX = 0xFFFF;
    public const UINT32_MAX = 0xFFFFFFFF;

    public static function assertUint16(int $value, string $field): void
    {
        if ($value < 0 || $value > self::UINT16_MAX) {
            throw IntegerOverflowException::forFieldRange($field, 16, $value, 0, self::UINT16_MAX);
        }
    }

    public static function assertUint32(int $value, string $field): void
    {
        if ($value < 0 || $value > self::UINT32_MAX) {
            throw IntegerOverflowException::forFieldRange($field, 32, $value, 0, self::UINT32_MAX);
        }
    }

    public static function assertUint64(int $value, string $field): void
    {
        if ($value < 0) {
            throw IntegerOverflowException::forFieldRange($field, 64, $value, 0, PHP_INT_MAX);
        }
    }
}
