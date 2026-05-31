<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * ULID generator for TigerBeetle account and transfer IDs.
 *
 * Generates monotonic 128-bit IDs with 48-bit timestamp
 * and 80-bit random component.
 *
 * ID byte layout (16 bytes little-endian):
 * Bytes [0..9]  = random (80-bit, cryptographically secure) — LSB side
 * Bytes [10..15] = timestamp (48-bit, milliseconds since Unix epoch) — MSB side
 *
 * This layout ensures monotonic ordering: the timestamp occupies the
 * most significant bytes, so a later timestamp always produces a
 * numerically larger ID regardless of the random component.
 */
final class Id
{
    private const RANDOM_BYTES = 10;

    private const TIMESTAMP_BYTES = 6;

    private const TOTAL_BYTES = 16;

    /** Byte offset where the timestamp starts (after random bytes) */
    private const TIMESTAMP_OFFSET = 10;

    /** Byte offset where the random starts */
    private const RANDOM_OFFSET = 0;

    private static int $lastTimestamp = 0;

    /** @var string 10-byte random component from last generation */
    private static string $lastRandomBytes = "\0\0\0\0\0\0\0\0\0\0";

    /**
     * Get the timestamp from the last generated ID.
     */
    public static function getLastTimestamp(): int
    {
        return self::$lastTimestamp;
    }

    /**
     * Get the random bytes from the last generated ID.
     *
     * @return string 10-byte binary string
     */
    public static function getLastRandomBytes(): string
    {
        return self::$lastRandomBytes;
    }

    /**
     * Reset internal state. Useful for testing.
     */
    public static function resetState(): void
    {
        self::$lastTimestamp = 0;
        self::$lastRandomBytes = "\0\0\0\0\0\0\0\0\0\0";
    }

    /**
     * Generate a new unique ID.
     *
     * The ID consists of:
     * - 48-bit timestamp (milliseconds since Unix epoch) — bytes 0-5
     * - 80-bit random component — bytes 6-15
     *
     * Monotonicity is guaranteed:
     * - If called with a new timestamp, fresh random bytes are generated.
     * - If called within the same millisecond, the random component
     *   is incremented by 1 (80-bit arithmetic).
     * - If the 80-bit random space is exhausted, the timestamp is
     *   force-advanced by 1 ms.
     */
    public static function generate(): Uint128
    {
        $timestamp = self::now();

        if ($timestamp > self::$lastTimestamp) {
            $randomBytes = \random_bytes(self::RANDOM_BYTES);
            self::$lastTimestamp = $timestamp;
            self::$lastRandomBytes = $randomBytes;
        } else {
            // Same millisecond — increment random for monotonicity
            $timestamp = self::$lastTimestamp;
            $randomBytes = self::incrementRandom(self::$lastRandomBytes);

            if ($randomBytes === null) {
                // 80-bit random space exhausted — force next timestamp
                $timestamp = self::$lastTimestamp + 1;
                $randomBytes = \random_bytes(self::RANDOM_BYTES);
            }

            self::$lastTimestamp = $timestamp;
            self::$lastRandomBytes = $randomBytes;
        }

        return self::pack($timestamp, self::$lastRandomBytes);
    }

    /**
     * Get current time in milliseconds.
     */
    private static function now(): int
    {
        return (int) (\microtime(true) * 1000);
    }

    /**
     * Increment a 10-byte (80-bit) little-endian value by 1.
     *
     * Returns the incremented bytes, or null if the value overflows
     * (all bits become 0, meaning the 80-bit space is exhausted).
     *
     * @param string $bytes 10-byte binary string
     * @return string|null 10-byte binary string or null on overflow
     */
    private static function incrementRandom(string $bytes): ?string
    {
        for ($i = 0; $i < self::RANDOM_BYTES; ++$i) {
            $byte = \ord($bytes[$i]);
            ++$byte;
            $bytes[$i] = \chr($byte & 0xFF);
            if ($byte <= 0xFF) {
                // No carry — done
                return $bytes;
            }
        }

        // All 10 bytes carried — 80-bit overflow
        return null;
    }

    /**
     * Pack timestamp (48-bit) and random (80-bit) into a 16-byte Uint128.
     *
     * Little-endian byte layout:
     * [0..9]  = random (10 bytes, LE) — LSB side
     * [10..15] = timestamp (6 bytes, LE) — MSB side
     *
     * This ordering ensures that the timestamp dominates the 128-bit
     * value for comparison, providing natural monotonic ordering.
     */
    private static function pack(int $timestamp, string $randomBytes): Uint128
    {
        $buffer = \str_repeat("\0", self::TOTAL_BYTES);

        // Write random bytes at offset 0 (LSB side)
        for ($i = 0; $i < self::RANDOM_BYTES; ++$i) {
            $buffer[self::RANDOM_OFFSET + $i] = $randomBytes[$i];
        }

        // Write timestamp (48-bit LE) at offset 10 (MSB side)
        for ($i = 0; $i < self::TIMESTAMP_BYTES; ++$i) {
            $buffer[self::TIMESTAMP_OFFSET + $i] = \chr($timestamp & 0xFF);
            $timestamp >>= 8;
        }

        return Uint128::fromBytes($buffer);
    }

    /**
     * Crockford Base32 character set.
     *
     * Standard: 0123456789ABCDEFGHJKMNPQRSTVWXYZ
     * (excludes I, L, O, U to avoid confusion with 1, L, 0, V).
     */
    private const CROCKFORD_CHARSET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Convert a Uint128 ID to a Crockford Base32 ULID string.
     *
     * The output is a 26-character string where:
     * - The first character encodes the most significant 5 bits
     *   (top 2 bits are always 0 padding)
     * - Subsequent characters encode the remaining 125 bits (25 × 5)
     * - Total: 26 characters = 130 bits (2 padding bits at the top)
     *
     * Example: "01ARZ3NDEKTSV4RRFFQ69G5FAV"
     */
    public static function toString(Uint128 $id): string
    {
        // Get 16 bytes in big-endian order (MSB first) for bit processing
        $bytes = \strrev($id->toBytes());

        $result = '';
        $charset = self::CROCKFORD_CHARSET;

        // 26 characters × 5 bits = 130 bits
        // The 130-bit stream: 2 zero padding bits + 128 data bits (MSB first)
        for ($i = 0; $i < 26; ++$i) {
            $bitStart = $i * 5;
            $value = 0;

            for ($j = 0; $j < 5; ++$j) {
                $absPos = $bitStart + $j;

                if ($absPos < 2) {
                    // Padding bits (always 0)
                    $bit = 0;
                } else {
                    $dataPos = $absPos - 2; // 0..127 within the 128-bit value
                    $byteIdx = \intdiv($dataPos, 8);
                    $bitIdx = 7 - ($dataPos % 8);
                    $bit = (\ord($bytes[$byteIdx]) >> $bitIdx) & 1;
                }

                $value = ($value << 1) | $bit;
            }

            $result .= $charset[$value];
        }

        \assert(\strlen($result) === 26, \sprintf(
            'ULID string must be exactly 26 characters, got %d',
            \strlen($result),
        ));

        return $result;
    }

    /**
     * Parse a Crockford Base32 ULID string back into a Uint128.
     *
     * Accepts:
     * - Standard Crockford characters (0-9, A-Z excluding I, L, O, U)
     * - Lowercase letters (case-insensitive)
     * - Common substitutions: I/i→1, L/l→1, O/o→0, U/u→0
     *
     * @throws \InvalidArgumentException if the string is not exactly 26 characters
     *                                    or contains invalid characters
     */
    public static function fromString(string $ulid): Uint128
    {
        if (\strlen($ulid) !== 26) {
            throw new \InvalidArgumentException(\sprintf(
                'ULID string must be exactly 26 characters, got %d',
                \strlen($ulid),
            ));
        }

        // Build character-to-value mapping lazily
        static $charMap = null;

        if ($charMap === null) {
            $charMap = self::buildCharMap();
        }

        // Decode bit by bit: 26 chars × 5 bits = 130 bits
        // First 2 bits are padding (must be 0), remaining 128 bits = value
        $bytes = \array_fill(0, 16, 0);

        for ($i = 0; $i < 26; ++$i) {
            $char = $ulid[$i];

            if (!isset($charMap[$char])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Invalid Crockford Base32 character at position %d: "%s"',
                    $i,
                    $char,
                ));
            }

            $charValue = $charMap[$char];

            // Process each of the 5 bits in the character (MSB first: bit 4 to bit 0)
            for ($j = 4; $j >= 0; --$j) {
                $absPos = $i * 5 + (4 - $j); // Absolute position in the 130-bit stream

                if ($absPos < 2) {
                    // Padding bits — must be zero
                    if ((($charValue >> $j) & 1) !== 0) {
                        throw new \InvalidArgumentException(
                            'ULID string has non-zero padding bits (value exceeds 128-bit range)',
                        );
                    }
                    continue;
                }

                $dataPos = $absPos - 2; // Position within the 128-bit value (0..127)
                $byteIdx = \intdiv($dataPos, 8);
                $bitIdx = 7 - ($dataPos % 8);

                if ((($charValue >> $j) & 1) !== 0) {
                    $bytes[$byteIdx] |= (1 << $bitIdx);
                }
            }
        }

        // $bytes is big-endian (MSB first), reverse to little-endian for Uint128
        return Uint128::fromBytes(\strrev(\pack('C*', ...$bytes)));
    }

    /**
     * Build the character-to-value map for Crockford Base32 decoding.
     *
     * Supports case-insensitive matching and common substitutions
     * (I→1, L→1, O→0, U→0).
     *
     * @return array<string, int>
     */
    private static function buildCharMap(): array
    {
        /** @var array<string, int> $map */
        $map = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
            '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
            'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14,
            'F' => 15, 'G' => 16, 'H' => 17, 'J' => 18, 'K' => 19,
            'M' => 20, 'N' => 21, 'P' => 22, 'Q' => 23, 'R' => 24,
            'S' => 25, 'T' => 26, 'V' => 27, 'W' => 28, 'X' => 29,
            'Y' => 30, 'Z' => 31,
            'a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14,
            'f' => 15, 'g' => 16, 'h' => 17, 'j' => 18, 'k' => 19,
            'm' => 20, 'n' => 21, 'p' => 22, 'q' => 23, 'r' => 24,
            's' => 25, 't' => 26, 'v' => 27, 'w' => 28, 'x' => 29,
            'y' => 30, 'z' => 31,
        ];

        // Crockford common substitutions: I/i→1, L/l→1, O/o→0, U/u→0
        $map['i'] = 1;
        $map['I'] = 1;
        $map['l'] = 1;
        $map['L'] = 1;
        $map['o'] = 0;
        $map['O'] = 0;
        $map['u'] = 0;
        $map['U'] = 0;

        return $map;
    }

    /**
     * Extract the 48-bit timestamp from a Uint128 ID.
     *
     * Useful for testing and debugging.
     */
    public static function extractTimestamp(Uint128 $id): int
    {
        $bytes = $id->toBytes();
        $ts = 0;
        // Read timestamp from bytes 10..15 (MSB side, LE)
        for ($i = self::TIMESTAMP_OFFSET + self::TIMESTAMP_BYTES - 1; $i >= self::TIMESTAMP_OFFSET; --$i) {
            $ts = ($ts << 8) | \ord($bytes[$i]);
        }

        return $ts;
    }

    /**
     * Extract the 80-bit random component from a Uint128 ID.
     *
     * @return string 10-byte binary string
     */
    public static function extractRandom(Uint128 $id): string
    {
        $bytes = $id->toBytes();

        return \substr($bytes, self::RANDOM_OFFSET, self::RANDOM_BYTES);
    }
}
