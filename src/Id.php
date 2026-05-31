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
