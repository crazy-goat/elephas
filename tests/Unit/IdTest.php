<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Id (ULID generator) class.
 *
 * @covers \CrazyGoat\Elephas\Id
 */
final class IdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Id::resetState();
    }

    // ──────────────────────────────────────────────
    //  Basic generate() tests
    // ──────────────────────────────────────────────

    public function testGenerateReturnsUint128(): void
    {
        $id = Id::generate();
        $this->assertInstanceOf(Uint128::class, $id);
    }

    public function testGeneratedIdIs16Bytes(): void
    {
        $id = Id::generate();
        $this->assertSame(16, \strlen($id->toBytes()));
    }

    public function testGeneratedIdsAreUnique(): void
    {
        $ids = [];
        $count = 1000;

        for ($i = 0; $i < $count; ++$i) {
            $ids[] = Id::generate()->toHex();
        }

        $unique = \array_unique($ids);
        $this->assertCount($count, $unique, 'All generated IDs must be unique');
    }

    public function testGenerateTimestampIsApproximatelyNow(): void
    {
        $before = (int) (\microtime(true) * 1000);
        $id = Id::generate();
        $after = (int) (\microtime(true) * 1000);

        $extracted = Id::extractTimestamp($id);

        $this->assertGreaterThanOrEqual($before - 1, $extracted);
        $this->assertLessThanOrEqual($after + 1, $extracted);
    }

    public function testGenerateTimestampIs48Bits(): void
    {
        $id = Id::generate();
        $ts = Id::extractTimestamp($id);

        // 48-bit max value = 2^48 - 1 = 281474976710655
        $this->assertLessThanOrEqual(281474976710655, $ts);
        $this->assertGreaterThan(0, $ts);
    }

    public function testGenerateRandomIs10Bytes(): void
    {
        $id = Id::generate();
        $random = Id::extractRandom($id);

        $this->assertSame(10, \strlen($random));
    }

    public function testGenerateRandomIsCryptographic(): void
    {
        // Verify that random bytes look like random data (not all zeros, not patterned)
        $id1 = Id::generate();
        $id2 = Id::generate();

        $random1 = Id::extractRandom($id1);
        $random2 = Id::extractRandom($id2);

        // Two consecutive calls should have different random components
        // (unless they happened in the same millisecond, in which case
        // monotonicity increments them)
        $this->assertNotSame($random1, $random2);
    }

    // ──────────────────────────────────────────────
    //  Monotonicity tests
    // ──────────────────────────────────────────────

    public function testGenerateIsMonotonic(): void
    {
        $previous = null;

        for ($i = 0; $i < 100; ++$i) {
            $current = Id::generate();

            if ($previous instanceof Uint128) {
                $this->assertGreaterThan(
                    $previous->toHex(),
                    $current->toHex(),
                    'IDs must be monotonically increasing in lexicographic (hex) order',
                );
            }

            $previous = $current;
        }
    }

    public function testMonotonicityInSameMillisecond(): void
    {
        // Force same-timestamp scenario by rapid generation
        $ids = [];
        for ($i = 0; $i < 50; ++$i) {
            $ids[] = Id::generate();
        }

        $idCount = \count($ids);
        for ($i = 1; $i < $idCount; ++$i) {
            $prev = $ids[$i - 1]->toHex();
            $curr = $ids[$i]->toHex();

            $this->assertGreaterThan(
                $prev,
                $curr,
                \sprintf('ID at position %d must be greater than previous', $i),
            );
        }
    }

    public function testMonotonicityWithSameTimestampRandomIncrements(): void
    {
        // Generate IDs in batches and verify timestamp + random ordering
        $ids = [];

        for ($i = 0; $i < 10; ++$i) {
            $ids[] = Id::generate();
        }

        $idCount = \count($ids);
        // Verify strict byte-by-byte ordering
        for ($i = 1; $i < $idCount; ++$i) {
            $prevBytes = $ids[$i - 1]->toBytes();
            $currBytes = $ids[$i]->toBytes();

            // Compare lexicographically as little-endian bytes
            // For monotonic IDs, the full 16-byte value must increase
            $result = \strcmp($currBytes, $prevBytes);
            $this->assertGreaterThan(
                0,
                $result,
                \sprintf(
                    'Raw byte value at position %d must be strictly greater than previous. '
                    . 'Prev hex: %s, Curr hex: %s',
                    $i,
                    $ids[$i - 1]->toHex(),
                    $ids[$i]->toHex(),
                ),
            );
        }
    }

    public function testMonotonicityAcrossTimestampBoundary(): void
    {
        // Test that IDs remain monotonic when crossing a timestamp boundary
        $id1 = Id::generate();

        // Artificially advance timestamp by manipulating internal state
        // Since we can't control time, generate enough IDs to ensure
        // at least one timestamp tick (unlikely to be in same ms for 1000 IDs)
        $lastId = $id1;
        for ($i = 0; $i < 1000; ++$i) {
            $currentId = Id::generate();
            $this->assertGreaterThan(
                $lastId->toHex(),
                $currentId->toHex(),
                'Monotonicity must hold across timestamp boundaries',
            );
            $lastId = $currentId;
        }
    }

    // ──────────────────────────────────────────────
    //  Timestamp extraction tests
    // ──────────────────────────────────────────────

    public function testExtractTimestampFromGeneratedId(): void
    {
        $id = Id::generate();
        $ts = Id::extractTimestamp($id);

        // Timestamp should be recent (within the last second)
        $now = (int) (\microtime(true) * 1000);
        $this->assertLessThanOrEqual($now, $ts);
        $this->assertGreaterThan($now - 5000, $ts); // within last 5 seconds
    }

    public function testExtractTimestampFromZero(): void
    {
        $zero = Uint128::zero();
        $ts = Id::extractTimestamp($zero);

        $this->assertSame(0, $ts);
    }

    // ──────────────────────────────────────────────
    //  Random extraction tests
    // ──────────────────────────────────────────────

    public function testExtractRandomFromGeneratedId(): void
    {
        $id = Id::generate();
        $random = Id::extractRandom($id);

        $this->assertSame(10, \strlen($random));

        // Random should not be all zeros
        $this->assertNotSame(\str_repeat("\0", 10), $random);
    }

    public function testExtractRandomFromZero(): void
    {
        $zero = Uint128::zero();
        $random = Id::extractRandom($zero);

        $this->assertSame(10, \strlen($random));
        $this->assertSame(\str_repeat("\0", 10), $random);
    }

    // ──────────────────────────────────────────────
    //  Internal state tests
    // ──────────────────────────────────────────────

    public function testResetStateClearsTimestamp(): void
    {
        Id::generate();
        Id::resetState();

        $this->assertSame(0, Id::getLastTimestamp());
    }

    public function testGetLastTimestampAfterGenerate(): void
    {
        $id = Id::generate();
        $extracted = Id::extractTimestamp($id);

        // getLastTimestamp should match the extracted timestamp
        $this->assertSame($extracted, Id::getLastTimestamp());
    }

    public function testGetLastRandomBytesAfterGenerate(): void
    {
        $id = Id::generate();
        $extractedRandom = Id::extractRandom($id);

        $this->assertSame($extractedRandom, Id::getLastRandomBytes());
    }

    // ──────────────────────────────────────────────
    //  Edge cases
    // ──────────────────────────────────────────────

    public function testGenerateAfterResetProducesValidId(): void
    {
        Id::generate();
        Id::resetState();

        $id = Id::generate();
        $this->assertInstanceOf(Uint128::class, $id);

        $ts = Id::extractTimestamp($id);
        $this->assertGreaterThan(0, $ts);
    }

    public function testGenerateIsDeterministicAfterReset(): void
    {
        // After reset, the first generated ID should have a timestamp near now
        Id::resetState();
        $before = (int) (\microtime(true) * 1000);
        $id = Id::generate();
        $after = (int) (\microtime(true) * 1000);

        $ts = Id::extractTimestamp($id);
        $this->assertGreaterThanOrEqual($before - 1, $ts);
        $this->assertLessThanOrEqual($after + 1, $ts);
    }

    // ──────────────────────────────────────────────
    //  Byte layout tests
    // ──────────────────────────────────────────────

    public function testByteLayoutTimestampInHighBytes(): void
    {
        // Verify that the timestamp is in the most significant bytes (10..15)
        // by constructing an ID with known values.
        //
        // Layout: [0..9] = random (10 bytes LE), [10..15] = timestamp (6 bytes LE)

        $buffer = \str_repeat("\0", 16);

        // Set random = 1 at bytes 0..9 (LE)
        $buffer[0] = "\x01";

        // Set timestamp = 1 at bytes 10..15 (LE)
        $buffer[10] = "\x01";

        $id = Uint128::fromBytes($buffer);
        $ts = Id::extractTimestamp($id);
        $random = Id::extractRandom($id);

        $this->assertSame(1, $ts, 'Timestamp should be 1 extracted from bytes 10..15');
        $this->assertSame(
            "\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00",
            $random,
            'Random should be 1 at bytes 0..9',
        );

        // Hex representation: random at low bytes, timestamp at high bytes
        // With LE bytes [random_10bytes][ts_6bytes], toHex() reverses to big-endian:
        // hex = [ts_6bytes_big_endian][random_10bytes_big_endian]
        // So the hex string should start with the timestamp value
        $hex = $id->toHex();
        // Timestamp 1 in BE = 000000000001 (12 hex chars)
        $this->assertStringStartsWith('000000000001', $hex);
    }

    public function testTimestampDominatesComparison(): void
    {
        // Two IDs with same random but different timestamps.
        // The one with larger timestamp must have larger hex value.
        $id1 = Uint128::fromBytes(
            "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"   // random = 0
            . "\x01\x00\x00\x00\x00\x00",                    // ts = 1
        );
        $id2 = Uint128::fromBytes(
            "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"   // random = 0
            . "\x02\x00\x00\x00\x00\x00",                    // ts = 2
        );

        $this->assertGreaterThan($id1->toHex(), $id2->toHex(), 'Later timestamp must produce larger hex value');
    }
}
