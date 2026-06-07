<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when an unknown/unrecognized native enum status value
 * is encountered during binary response parsing.
 *
 * This indicates a native library version mismatch or a corrupt response.
 */
final class UnknownStatusException extends \RuntimeException implements ElephasExceptionInterface
{
    /**
     * @param class-string $enumClass The fully qualified backed enum class name
     * @param int          $value     The unrecognized integer value
     */
    public static function forEnum(string $enumClass, int $value): self
    {
        $knownValues = \array_map(
            static fn(\BackedEnum $case): string => (string) $case->value,
            $enumClass::cases(),
        );

        return new self(
            \sprintf(
                'Unknown %s value %d. This may indicate a native library version mismatch or a corrupt response. Known values: %s',
                $enumClass,
                $value,
                \implode(', ', $knownValues),
            ),
        );
    }
}
