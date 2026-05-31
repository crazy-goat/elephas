<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Uint128;

use CrazyGoat\Elephas\Exception\IntegerOverflowException;

final class Uint128
{
    /**
     * @param int $low  Least significant 64 bits (unsigned, stored as signed int64)
     * @param int $high Most significant 64 bits (unsigned, stored as signed int64)
     */
    private function __construct(
        private readonly int $low,
        private readonly int $high,
    ) {}

    // ──────────────────────────────────────────────
    //  Factory methods
    // ──────────────────────────────────────────────

    public static function zero(): self
    {
        return new self(0, 0);
    }

    /**
     * Create from a PHP int (signed 64-bit).
     * The value is treated as unsigned 64-bit (zero-extended to 128-bit).
     */
    public static function fromInt(int $value): self
    {
        return new self($value, 0);
    }

    /**
     * Create from a decimal string representation of an unsigned 128-bit integer.
     *
     * @throws IntegerOverflowException if the value exceeds 2^128-1
     */
    public static function fromString(string $decimal): self
    {
        $bytes = array_fill(0, 16, 0);

        for ($i = 0, $len = \strlen($decimal); $i < $len; ++$i) {
            $digit = \ord($decimal[$i]) - 48;

            if ($digit < 0 || $digit > 9) {
                throw new \ValueError("Invalid decimal character: {$decimal[$i]}");
            }

            // multiply by 10 (shift-left 3 + shift-left 1)
            $carry = 0;
            for ($j = 15; $j >= 0; --$j) {
                $value = ($bytes[$j] << 3) + ($bytes[$j] << 1) + $carry;
                $bytes[$j] = $value & 0xFF;
                $carry = $value >> 8;
            }

            if ($carry !== 0) {
                throw IntegerOverflowException::forValue($decimal);
            }

            // add digit
            $carry = $digit;
            for ($j = 15; $j >= 0 && $carry !== 0; --$j) {
                $value = $bytes[$j] + $carry;
                $bytes[$j] = $value & 0xFF;
                $carry = $value >> 8;
            }

            if ($carry !== 0) {
                throw IntegerOverflowException::forValue($decimal);
            }
        }

        $low = self::bytesToUint64(\array_slice($bytes, 0, 8));
        $high = self::bytesToUint64(\array_slice($bytes, 8, 8));

        return new self($low, $high);
    }

    /**
     * Create from low and high 64-bit parts (unsigned).
     */
    public static function fromParts(int $low, int $high): self
    {
        return new self($low, $high);
    }

    /**
     * Create from 16 bytes in little-endian format.
     */
    public static function fromBytes(string $bytes): self
    {
        if (\strlen($bytes) !== 16) {
            throw new \ValueError('Uint128 must be exactly 16 bytes');
        }

        $parts = \unpack('Plow/Phigh', $bytes);

        return new self($parts['low'], $parts['high']);
    }

    /**
     * Create from a hex string (big-endian, with or without 0x prefix).
     */
    public static function fromHex(string $hex): self
    {
        if (\str_starts_with($hex, '0x') || \str_starts_with($hex, '0X')) {
            $hex = \substr($hex, 2);
        }

        $hex = \str_pad($hex, 32, '0', \STR_PAD_LEFT);

        if (\strlen($hex) !== 32) {
            throw new \ValueError('Hex string exceeds 128-bit range');
        }

        $bytes = \hex2bin($hex);
        // hex string is big-endian, reverse to little-endian
        $bytes = \strrev($bytes);

        return self::fromBytes($bytes);
    }

    // ──────────────────────────────────────────────
    //  Conversion methods
    // ──────────────────────────────────────────────

    /**
     * Convert to PHP int (signed 64-bit).
     *
     * @throws IntegerOverflowException if the value exceeds PHP_INT_MAX
     */
    public function toInt(): int
    {
        if ($this->high !== 0) {
            throw IntegerOverflowException::forIntOverflow($this->toString());
        }

        // If bit 63 is set, value exceeds signed 64-bit range
        if ($this->low < 0) {
            throw IntegerOverflowException::forIntOverflow($this->toString());
        }

        return $this->low;
    }

    /**
     * Convert to float. May lose precision for values > 2^53.
     */
    public function toFloat(): float
    {
        return (float) $this->toString();
    }

    /**
     * Convert to decimal string representation.
     */
    public function toString(): string
    {
        if ($this->low === 0 && $this->high === 0) {
            return '0';
        }

        $bytes = \array_merge(
            self::uint64ToBytes($this->low),
            self::uint64ToBytes($this->high),
        );

        $result = '';
        $isZero = false;

        while (!$isZero) {
            $remainder = 0;
            $isZero = true;

            for ($i = 0; $i < 16; ++$i) {
                $value = ($remainder << 8) | $bytes[$i];
                $digit = \intdiv($value, 10);
                $remainder = $value % 10;
                $bytes[$i] = $digit;

                if ($digit !== 0) {
                    $isZero = false;
                }
            }

            $result = \chr(0x30 + $remainder) . $result;
        }

        return $result;
    }

    /**
     * Convert to hex string (big-endian, lowercase, no prefix).
     */
    public function toHex(): string
    {
        $bytes = $this->toBytes();
        // reverse to big-endian for hex output
        $bytes = \strrev($bytes);

        return \bin2hex($bytes);
    }

    /**
     * Convert to 16 bytes in little-endian format.
     */
    public function toBytes(): string
    {
        return \pack('P', $this->low) . \pack('P', $this->high);
    }

    /**
     * @return array{low: int, high: int}
     */
    public function toArray(): array
    {
        return ['low' => $this->low, 'high' => $this->high];
    }

    public function isZero(): bool
    {
        return $this->low === 0 && $this->high === 0;
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * Convert unsigned 64-bit value (stored as PHP int) to 8 bytes LE.
     *
     * @param int $value Signed int64 representing unsigned value
     * @return int[] Array of 8 bytes (0-255)
     */
    private static function uint64ToBytes(int $value): array
    {
        $bytes = [];
        for ($i = 0; $i < 8; ++$i) {
            $bytes[] = $value & 0xFF;
            $value >>= 8;
        }

        return $bytes;
    }

    /**
     * Convert 8 bytes LE to unsigned 64-bit value (stored as PHP int).
     *
     * @param int[] $bytes Array of 8 bytes
     */
    private static function bytesToUint64(array $bytes): int
    {
        $value = 0;
        for ($i = 7; $i >= 0; --$i) {
            $value = ($value << 8) | ($bytes[$i] & 0xFF);
        }

        return $value;
    }
}
