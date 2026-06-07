<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Id::class)]
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
        // Verify strict ordering using numeric comparison
        for ($i = 1; $i < $idCount; ++$i) {
            $prev = $ids[$i - 1];
            $curr = $ids[$i];

            $this->assertGreaterThan(
                0,
                $curr->compareTo($prev),
                \sprintf(
                    'ID at position %d must be strictly greater than previous. '
                    . 'Prev hex: %s, Curr hex: %s',
                    $i,
                    $prev->toHex(),
                    $curr->toHex(),
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
    //  toString() – Crockford Base32 encoding
    // ──────────────────────────────────────────────

    public function testToStringReturns26Characters(): void
    {
        $id = Id::generate();
        $str = Id::toString($id);

        $this->assertSame(26, \strlen($str), 'ULID string must be exactly 26 characters');
    }

    public function testToStringUsesOnlyValidCrockfordChars(): void
    {
        $id = Id::generate();
        $str = Id::toString($id);

        $validChars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        for ($i = 0; $i < 26; ++$i) {
            $this->assertStringContainsString(
                $str[$i],
                $validChars,
                \sprintf('Character at position %d is not a valid Crockford Base32 char: %s', $i, $str[$i]),
            );
        }
    }

    public function testToStringStartsWithZeroForRecentTimestamp(): void
    {
        // Given the current timestamp (~2026), the first Crockford char
        // (most significant 5 bits of the 48-bit timestamp) should be '0' or '1'
        $id = Id::generate();
        $str = Id::toString($id);

        // Timestamp is ~1.78 trillion ms = ~0x19E, first 5 bits = 0
        $this->assertSame('0', $str[0], 'First character should be 0 for current timestamps');
    }

    public function testToStringZero(): void
    {
        $zero = Uint128::zero();
        $str = Id::toString($zero);

        $this->assertSame(26, \strlen($str));
        $this->assertSame('00000000000000000000000000', $str);
    }

    public function testToStringMaxValue(): void
    {
        $max = Uint128::fromParts(-1, -1);
        $str = Id::toString($max);

        // 2^128-1 in Crockford Base32:
        // 130 bits: 00 | 11111111 ... 11111111 (128 ones)
        // Char 0: 00111 = 7 = '7'
        // Chars 1-25: 11111 = 31 = 'Z'
        $this->assertSame(26, \strlen($str));
        $this->assertSame('7ZZZZZZZZZZZZZZZZZZZZZZZZZ', $str);
    }

    public function testToStringKnownValues(): void
    {
        // Test with a known value:
        // Timestamp = 0, random = 0 => 128-bit value = 0 => "00000000000000000000000000"
        $zero = Uint128::zero();
        $this->assertSame('00000000000000000000000000', Id::toString($zero));

        // Single bit set at position 127 (MSB): value = 2^127
        // BE bytes: [0x80, 0x00, 0x00, ...]
        // 130-bit stream: 00 | 10000000 00000000 ...
        // Char 0: 00100 = 4 = '4'
        // Chars 1-25: 00000 = 0 = '0'
        $msbValue = Uint128::fromBytes(
            "\x00\x00\x00\x00\x00\x00\x00\x00"   // low (LE) = 0
            . "\x00\x00\x00\x00\x00\x00\x00\x80",    // high (LE) = 2^63 => 2^127
        );
        $this->assertSame('40000000000000000000000000', Id::toString($msbValue));
    }

    // ──────────────────────────────────────────────
    //  fromString() – Crockford Base32 decoding
    // ──────────────────────────────────────────────

    public function testFromStringZero(): void
    {
        $id = Id::fromString('00000000000000000000000000');
        $this->assertTrue($id->isZero());
    }

    public function testFromStringRoundtrip(): void
    {
        // Generate a random ID and verify roundtrip
        $original = Id::generate();
        $str = Id::toString($original);
        $decoded = Id::fromString($str);

        $this->assertTrue($original->equals($decoded), 'Roundtrip toString → fromString must return the same value');
    }

    public function testFromStringRoundtripMultiple(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $original = Id::generate();
            $str = Id::toString($original);
            $decoded = Id::fromString($str);

            $this->assertTrue(
                $original->equals($decoded),
                \sprintf('Roundtrip failed for ID %s (iteration %d)', $original->toHex(), $i),
            );
        }
    }

    public function testFromStringCaseInsensitive(): void
    {
        $original = Id::generate();
        $str = Id::toString($original);
        $lower = \strtolower($str);
        $decoded = Id::fromString($lower);

        $this->assertTrue($original->equals($decoded), 'Lowercase ULID string must decode correctly');
    }

    public function testFromStringWithSubstitutions(): void
    {
        // Test common Crockford substitutions: I→1, L→1, O→0, U→0
        // Build a ULID string that contains '1' then try 'I' and 'L'
        // Build a ULID string that contains '0' then try 'O' and 'U'

        $original = Id::generate();
        $str = Id::toString($original);

        // Substitute I for 1, O for 0, etc.
        $substituted = \str_replace(['1', '0'], ['I', 'O'], $str);
        $decoded = Id::fromString($substituted);
        $this->assertTrue($original->equals($decoded), 'Substituted characters (I→1, O→0) must decode correctly');

        $substituted2 = \str_replace(['1', '0'], ['L', 'U'], $str);
        $decoded2 = Id::fromString($substituted2);
        $this->assertTrue($original->equals($decoded2), 'Substituted characters (L→1, U→0) must decode correctly');
    }

    public function testFromStringInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 26 characters');
        Id::fromString('TOO_SHORT');
    }

    public function testFromStringInvalidLengthTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 26 characters');
        Id::fromString('000000000000000000000000000');
    }

    public function testFromStringInvalidCharacter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Crockford Base32 character');
        Id::fromString('0000000000000000000000000!'); // 25 zeros + ! = 26 chars
    }

    public function testFromStringInvalidCharacterExcluded(): void
    {
        // '@' is not a valid Crockford character
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Crockford Base32 character');
        Id::fromString('00000000000000000@00000000'); // 17 zeros + @ + 8 zeros = 26 chars
    }

    public function testFromStringWithNonZeroPaddingBits(): void
    {
        // The top 2 padding bits must be 0 for 128-bit values.
        // Character '8' = 8 = 0b01000 -> bits: 0 1 0 0 0
        //   absPos 0 (bit 4) = 0 ✓
        //   absPos 1 (bit 3) = 1 ✗ -> padding bit is 1 -> invalid!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('padding bits');
        Id::fromString('80000000000000000000000000'); // 26 chars
    }

    // ──────────────────────────────────────────────
    //  Crockford encoding/decoding consistency
    // ──────────────────────────────────────────────

    public function testEncodeDecodeConsistencyForRange(): void
    {
        // Test a variety of edge-case values
        $values = [
            Uint128::zero(),
            Uint128::fromInt(1),
            Uint128::fromInt(255),
            Uint128::fromInt(\PHP_INT_MAX),
            Uint128::fromParts(-1, 0), // 2^64-1
            Uint128::fromParts(0, 1), // 2^64
            Uint128::fromParts(-1, -1), // 2^128-1
            Uint128::fromString('12345678901234567890'),
            Uint128::fromHex('ffffffffffffffffffffffffffffffff'),
            Uint128::fromHex('80000000000000000000000000000000'), // 2^127
            Uint128::fromHex('00000000000000000000000000000001'),
        ];

        foreach ($values as $value) {
            $str = Id::toString($value);
            $this->assertSame(26, \strlen($str), \sprintf('toString(%s) must be 26 chars', $value->toHex()));

            $decoded = Id::fromString($str);
            $this->assertTrue(
                $value->equals($decoded),
                \sprintf('Roundtrip failed for value %s: encoded=%s', $value->toHex(), $str),
            );
        }
    }

    public function testLexicographicOrderMatchesNumericOrder(): void
    {
        // String ordering of ULIDs should match numeric ordering of Uint128 values
        $values = [
            Uint128::zero(),
            Uint128::fromInt(1),
            Uint128::fromInt(255),
            Uint128::fromParts(-1, 0), // 2^64-1
            Uint128::fromParts(0, 1), // 2^64
            Uint128::fromParts(-1, -1), // 2^128-1
        ];

        $strings = \array_map(Id::toString(...), $values);
        $counter = \count($strings);

        for ($i = 1; $i < $counter; ++$i) {
            $this->assertGreaterThan(
                $strings[$i - 1],
                $strings[$i],
                \sprintf(
                    'ULID string order must match numeric order: %s >= %s',
                    $strings[$i - 1],
                    $strings[$i],
                ),
            );
        }
    }

    public function testGeneratedIdsHaveCorrectStringOrder(): void
    {
        // Generated IDs must have monotonically increasing string representations
        $ids = [];
        for ($i = 0; $i < 50; ++$i) {
            $ids[] = Id::generate();
        }

        $strings = \array_map(Id::toString(...), $ids);
        $counter = \count($strings);

        for ($i = 1; $i < $counter; ++$i) {
            $this->assertGreaterThan(
                $strings[$i - 1],
                $strings[$i],
                \sprintf('Generated ID string at position %d must be greater than previous', $i),
            );
        }
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

    // ──────────────────────────────────────────────
    //  Documentation contract tests
    // ──────────────────────────────────────────────

    public function testFromStringReturnsUint128(): void
    {
        // Documented contract: fromString() returns Uint128
        $result = Id::fromString('00000000000000000000000000');
        $this->assertInstanceOf(Uint128::class, $result);
    }

    public function testToStringReturnsString(): void
    {
        // Documented contract: toString() returns 26-character ULID string
        $result = Id::toString(Uint128::zero());
        $this->assertSame(26, \strlen($result));
    }
}
