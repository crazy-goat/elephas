<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Uint128;

use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uint128::class)]
class Uint128Test extends TestCase
{
    // ──────────────────────────────────────────────
    //  Factory methods
    // ──────────────────────────────────────────────

    public function testZero(): void
    {
        $zero = Uint128::zero();
        $this->assertTrue($zero->isZero());
        $this->assertSame('0', $zero->toString());
        $this->assertSame(0, $zero->toInt());
        $this->assertSame(0.0, $zero->toFloat());
    }

    public function testFromInt(): void
    {
        $val = Uint128::fromInt(42);
        $this->assertSame(42, $val->toInt());
        $this->assertSame('42', $val->toString());

        $max = Uint128::fromInt(\PHP_INT_MAX);
        $this->assertSame(\PHP_INT_MAX, $max->toInt());
        $this->assertSame((string) \PHP_INT_MAX, $max->toString());

        $min = Uint128::fromInt(-1);
        // -1 as unsigned 64-bit = 2^64 - 1 = 18446744073709551615
        $this->assertSame('18446744073709551615', $min->toString());
    }

    /**
     * @param array{low: int, high: int} $expectedParts
     */
    #[DataProvider('provideValidStrings')]
    public function testFromString(string $decimal, string $expectedHex, array $expectedParts): void
    {
        $val = Uint128::fromString($decimal);
        $this->assertSame($expectedHex, $val->toHex(), "fromString($decimal) should produce hex $expectedHex");
        $this->assertSame($decimal, $val->toString(), "Round-trip toString() should match");
        $this->assertSame($expectedParts['low'], $val->toArray()['low']);
        $this->assertSame($expectedParts['high'], $val->toArray()['high']);
    }

    /**
     * @return array<string, array{string, string, array{low: int, high: int}}>
     */
    public static function provideValidStrings(): array
    {
        return [
            'zero' => ['0', '00000000000000000000000000000000', ['low' => 0, 'high' => 0]],
            'one' => ['1', '00000000000000000000000000000001', ['low' => 1, 'high' => 0]],
            'small' => ['255', '000000000000000000000000000000ff', ['low' => 255, 'high' => 0]],
            '2^64-1' => [
                '18446744073709551615',
                '0000000000000000ffffffffffffffff',
                ['low' => -1, 'high' => 0],
            ],
            '2^64' => [
                '18446744073709551616',
                '00000000000000010000000000000000',
                ['low' => 0, 'high' => 1],
            ],
            '2^128-1' => [
                '340282366920938463463374607431768211455',
                'ffffffffffffffffffffffffffffffff',
                ['low' => -1, 'high' => -1],
            ],
            'large' => [
                '123456789012345678901234567890',
                '000000018ee90ff6c373e0ee4e3f0ad2',
                ['low' => -4362896299872285998, 'high' => 6692605942],
            ],
        ];
    }

    public function testFromStringThrowsOnInvalidCharacter(): void
    {
        $this->expectException(\ValueError::class);
        Uint128::fromString('12a45');
    }

    public function testFromStringThrowsOnOverflow(): void
    {
        $this->expectException(IntegerOverflowException::class);
        // 2^128 = 340282366920938463463374607431768211456 (too large by 1)
        Uint128::fromString('340282366920938463463374607431768211456');
    }

    public function testFromParts(): void
    {
        $val = Uint128::fromParts(1, 2);
        $this->assertSame(['low' => 1, 'high' => 2], $val->toArray());
        // fromParts(1, 2): low=1, high=2 => value = 2 * 2^64 + 1
        $this->assertSame('36893488147419103233', $val->toString());
    }

    public function testFromBytes(): void
    {
        $bytes = \pack('P', 1) . \pack('P', 2); // low=1, high=2 in LE
        $val = Uint128::fromBytes($bytes);
        $this->assertSame(1, $val->toArray()['low']);
        $this->assertSame(2, $val->toArray()['high']);
    }

    public function testFromBytesThrowsOnWrongLength(): void
    {
        $this->expectException(\ValueError::class);
        Uint128::fromBytes('too short');
    }

    public function testFromHex(): void
    {
        $val = Uint128::fromHex('ff');
        $this->assertSame(255, $val->toInt());

        $val2 = Uint128::fromHex('0xFF');
        $this->assertSame(255, $val2->toInt());

        // Full 128-bit hex
        $val3 = Uint128::fromHex('00000000000000010000000000000000');
        $this->assertSame('18446744073709551616', $val3->toString());
    }

    public function testFromHexWith0xPrefix(): void
    {
        $val = Uint128::fromHex('0xabcdef0123456789');
        // Full 128-bit hex output includes leading zeros
        $this->assertSame('0000000000000000abcdef0123456789', $val->toHex());
    }

    // ──────────────────────────────────────────────
    //  Conversion methods
    // ──────────────────────────────────────────────

    public function testToInt(): void
    {
        $val = Uint128::fromInt(12345);
        $this->assertSame(12345, $val->toInt());

        // PHP_INT_MAX should work
        $max = Uint128::fromInt(\PHP_INT_MAX);
        $this->assertSame(\PHP_INT_MAX, $max->toInt());

        // fromInt with high=0 but low fits signed
        $this->assertSame(42, Uint128::fromInt(42)->toInt());
    }

    public function testToIntThrowsOnOverflow(): void
    {
        $val = Uint128::fromParts(0, 1); // 2^64
        $this->expectException(IntegerOverflowException::class);
        $val->toInt();
    }

    public function testToIntThrowsWhenLowHasSignBit(): void
    {
        // low < 0 means bit 63 is set, value > 2^63-1 as unsigned
        $val = Uint128::fromParts(-1, 0); // 2^64-1
        $this->expectException(IntegerOverflowException::class);
        $val->toInt();
    }

    public function testToFloat(): void
    {
        $val = Uint128::fromInt(123);
        $this->assertSame(123.0, $val->toFloat());

        $large = Uint128::fromString('12345678901234567890');
        $float = $large->toFloat();
        $this->assertGreaterThan(1.0e19, $float);

        $floatVal = Uint128::zero()->toFloat();
        $this->assertSame(0.0, $floatVal);
    }

    public function testToFloatPrecisionLoss(): void
    {
        $val = Uint128::fromString('9007199254740993');
        $asFloat = $val->toFloat();
        $this->assertNotSame('9007199254740993', (string) $asFloat);
    }

    public function testToString(): void
    {
        $this->assertSame('0', Uint128::zero()->toString());
        $this->assertSame('1', Uint128::fromInt(1)->toString());
        $this->assertSame('255', Uint128::fromInt(255)->toString());

        $max = Uint128::fromParts(-1, -1);
        $this->assertSame('340282366920938463463374607431768211455', $max->toString());

        $this->assertSame('18446744073709551615', Uint128::fromParts(-1, 0)->toString());
    }

    public function testToStringNeverThrows(): void
    {
        $values = [
            Uint128::zero(),
            Uint128::fromInt(1),
            Uint128::fromInt(\PHP_INT_MAX),
            Uint128::fromParts(-1, 0),
            Uint128::fromParts(0, 1),
            Uint128::fromParts(-1, -1),
            Uint128::fromParts(12345, 67890),
            Uint128::fromString('123456789012345678901234567890123456789'),
        ];

        foreach ($values as $val) {
            $str = $val->toString();
            $this->assertNotSame('', $str, 'toString should never return empty string');
            $this->assertMatchesRegularExpression('/^\d+$/', $str);
        }
    }

    public function testToHex(): void
    {
        $this->assertSame('00000000000000000000000000000000', Uint128::zero()->toHex());
        $this->assertSame('00000000000000000000000000000001', Uint128::fromInt(1)->toHex());
        $this->assertSame('000000000000000000000000000000ff', Uint128::fromInt(255)->toHex());

        $val = Uint128::fromParts(-1, -1);
        $this->assertSame('ffffffffffffffffffffffffffffffff', $val->toHex());
    }

    public function testToBytes(): void
    {
        $val = Uint128::fromParts(1, 2);
        $bytes = $val->toBytes();
        $this->assertSame(16, \strlen($bytes));
        $this->assertSame(\pack('P', 1) . \pack('P', 2), $bytes);

        // Round-trip
        $val2 = Uint128::fromBytes($bytes);
        $this->assertTrue($val->equals($val2));
    }

    public function testToBytesZero(): void
    {
        $bytes = Uint128::zero()->toBytes();
        $this->assertSame(16, \strlen($bytes));
        $this->assertSame(\str_repeat("\x00", 16), $bytes);
    }

    public function testToBytesMaxUint128(): void
    {
        $bytes = Uint128::fromParts(-1, -1)->toBytes();
        $this->assertSame(16, \strlen($bytes));
        $this->assertSame(\str_repeat("\xff", 16), $bytes);
    }

    public function testToBytesCrosses64Bit(): void
    {
        // Value with both low and high non-zero: high=2, low=1 => 2*2^64 + 1
        $val = Uint128::fromParts(1, 2);
        $bytes = $val->toBytes();
        $this->assertSame(16, \strlen($bytes));
        // little-endian: low part (1) first 8 bytes, high part (2) last 8 bytes
        $this->assertSame(\pack('P', 1) . \pack('P', 2), $bytes);

        // Cross boundary: low = -1 (all low bits set), high = 1
        $val2 = Uint128::fromParts(-1, 1);
        $bytes2 = $val2->toBytes();
        $this->assertSame(\pack('P', -1) . \pack('P', 1), $bytes2);
    }

    public function testToBytesLittleEndian(): void
    {
        // Verify byte order: Uint128(1) should have LSB=1 at byte 0
        $bytes = Uint128::fromInt(1)->toBytes();
        $this->assertSame(1, \ord($bytes[0]), 'Byte 0 should be 1 (LSB)');
        $this->assertSame(0, \ord($bytes[1]), 'Byte 1 should be 0');

        // 256 = 0x100, byte 0 should be 0, byte 1 should be 1
        $bytes256 = Uint128::fromInt(256)->toBytes();
        $this->assertSame(0, \ord($bytes256[0]));
        $this->assertSame(1, \ord($bytes256[1]));
    }

    public function testToArray(): void
    {
        $val = Uint128::fromParts(123, 456);
        $arr = $val->toArray();
        $this->assertSame(['low' => 123, 'high' => 456], $arr);
    }

    public function testIsZero(): void
    {
        $this->assertTrue(Uint128::zero()->isZero());
        $this->assertTrue(Uint128::fromInt(0)->isZero());
        $this->assertTrue(Uint128::fromString('0')->isZero());
        $this->assertFalse(Uint128::fromInt(1)->isZero());
        $this->assertFalse(Uint128::fromParts(0, 1)->isZero());
    }

    // ──────────────────────────────────────────────
    //  equals()
    // ──────────────────────────────────────────────

    public function testEquals(): void
    {
        $a = Uint128::fromInt(42);
        $b = Uint128::fromInt(42);
        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));

        $c = Uint128::fromInt(43);
        $this->assertFalse($a->equals($c));

        // Same value from different constructors
        $d = Uint128::fromString('42');
        $this->assertTrue($a->equals($d));

        // Zero
        $this->assertTrue(Uint128::zero()->equals(Uint128::fromInt(0)));

        // Large values
        $e = Uint128::fromParts(-1, -1);
        $f = Uint128::fromString('340282366920938463463374607431768211455');
        $this->assertTrue($e->equals($f));

        // Different high parts
        $this->assertFalse(Uint128::fromInt(0)->equals(Uint128::fromParts(0, 1)));
    }

    // ──────────────────────────────────────────────
    //  compareTo()
    // ──────────────────────────────────────────────

    public function testCompareToEqualValues(): void
    {
        $a = Uint128::fromInt(100);
        $b = Uint128::fromInt(100);
        $this->assertSame(0, $a->compareTo($b));
        $this->assertSame(0, $b->compareTo($a));

        // Zero
        $this->assertSame(0, Uint128::zero()->compareTo(Uint128::fromInt(0)));

        // Max
        $max = Uint128::fromParts(-1, -1);
        $this->assertSame(0, $max->compareTo($max));
    }

    public function testCompareToLessThan(): void
    {
        $small = Uint128::fromInt(10);
        $large = Uint128::fromInt(20);
        $this->assertSame(-1, $small->compareTo($large));
    }

    public function testCompareToGreaterThan(): void
    {
        $small = Uint128::fromInt(10);
        $large = Uint128::fromInt(20);
        $this->assertSame(1, $large->compareTo($small));
    }

    public function testCompareToAcrossHighBoundary(): void
    {
        // 2^64 - 1 (low = -1, high = 0)
        $belowBoundary = Uint128::fromParts(-1, 0);
        // 2^64 (low = 0, high = 1)
        $aboveBoundary = Uint128::fromParts(0, 1);

        $this->assertSame(-1, $belowBoundary->compareTo($aboveBoundary));
        $this->assertSame(1, $aboveBoundary->compareTo($belowBoundary));
    }

    public function testCompareToUnsignedSemantics(): void
    {
        // PHP_INT_MAX as unsigned is less than -1 as unsigned
        // -1 as unsigned = 2^64-1 > PHP_INT_MAX
        $a = Uint128::fromInt(\PHP_INT_MAX);    // low = PHP_INT_MAX, high = 0
        $b = Uint128::fromParts(-1, 0);          // low = -1 (2^64-1), high = 0

        $this->assertSame(-1, $a->compareTo($b), 'PHP_INT_MAX < 2^64-1 as unsigned');
        $this->assertSame(1, $b->compareTo($a));
    }

    public function testCompareToLargeHighValues(): void
    {
        // Both have high parts, different
        $a = Uint128::fromParts(0, 100);
        $b = Uint128::fromParts(0, 200);
        $this->assertSame(-1, $a->compareTo($b));
        $this->assertSame(1, $b->compareTo($a));
    }

    public function testCompareToWithNegativeHigh(): void
    {
        // high = -1 means high 64 bits = 2^64-1 (max possible)
        $a = Uint128::fromParts(0, 0);     // value = 0
        $b = Uint128::fromParts(0, -1);    // value = (2^64-1) * 2^64

        $this->assertSame(-1, $a->compareTo($b));
    }

    #[DataProvider('provideCompareToCases')]
    public function testCompareToDataProvider(string $aDesc, Uint128 $a, string $bDesc, Uint128 $b, int $expected): void
    {
        $this->assertSame(
            $expected,
            $a->compareTo($b),
            "Comparing {$aDesc} to {$bDesc}",
        );
        // Verify reflexivity
        $this->assertSame(
            -$expected,
            $b->compareTo($a),
            "Reverse comparison of {$bDesc} to {$aDesc}",
        );
    }

    /**
     * @return array<string, array{string, Uint128, string, Uint128, int}>
     */
    public static function provideCompareToCases(): array
    {
        return [
            'zero vs one' => ['0', Uint128::zero(), '1', Uint128::fromInt(1), -1],
            'one vs 2^64-1' => ['1', Uint128::fromInt(1), '2^64-1', Uint128::fromParts(-1, 0), -1],
            '2^64-1 vs 2^64' => ['2^64-1', Uint128::fromParts(-1, 0), '2^64', Uint128::fromParts(0, 1), -1],
            '2^64 vs 2^128-1' => ['2^64', Uint128::fromParts(0, 1), '2^128-1', Uint128::fromParts(-1, -1), -1],
            'equal large' => ['large-a', Uint128::fromString('12345678901234567890'), 'large-b', Uint128::fromString('12345678901234567890'), 0],
        ];
    }

    // ──────────────────────────────────────────────
    //  Transitive compareTo
    // ──────────────────────────────────────────────

    public function testCompareToTransitive(): void
    {
        $a = Uint128::fromInt(10);
        $b = Uint128::fromInt(20);
        $c = Uint128::fromInt(30);

        $this->assertSame(-1, $a->compareTo($b));
        $this->assertSame(-1, $b->compareTo($c));
        $this->assertSame(-1, $a->compareTo($c));
    }

    // ──────────────────────────────────────────────
    //  Consistency: equals vs compareTo
    // ──────────────────────────────────────────────

    #[DataProvider('provideConsistencyCases')]
    public function testEqualsConsistentWithCompareTo(Uint128 $a, Uint128 $b): void
    {
        $cmp = $a->compareTo($b);
        $eq = $a->equals($b);

        if ($cmp === 0) {
            $this->assertTrue($eq, 'compareTo=0 implies equals=true');
        } else {
            $this->assertFalse($eq, 'compareTo!=0 implies equals=false');
        }
    }

    /**
     * @return array<string, array{Uint128, Uint128}>
     */
    public static function provideConsistencyCases(): array
    {
        return [
            'zero-equal' => [Uint128::zero(), Uint128::fromInt(0)],
            'one-equal' => [Uint128::fromInt(1), Uint128::fromInt(1)],
            'zero-one-diff' => [Uint128::zero(), Uint128::fromInt(1)],
            'boundary-diff' => [Uint128::fromParts(-1, 0), Uint128::fromParts(0, 1)],
            'max-equal' => [Uint128::fromParts(-1, -1), Uint128::fromParts(-1, -1)],
            'max-vs-zero' => [Uint128::fromParts(-1, -1), Uint128::zero()],
        ];
    }

    // ──────────────────────────────────────────────
    //  Edge cases
    // ──────────────────────────────────────────────

    public function testRoundTripHex(): void
    {
        $hexValues = [
            '00000000000000000000000000000000',
            '00000000000000000000000000000001',
            'ffffffffffffffffffffffffffffffff',
            '1234567890abcdef1234567890abcdef',
            '80000000000000000000000000000000',
        ];

        foreach ($hexValues as $hex) {
            $val = Uint128::fromHex($hex);
            $this->assertSame($hex, $val->toHex(), "Round-trip hex: $hex");
        }
    }

    public function testRoundTripString(): void
    {
        $strings = [
            '0',
            '1',
            '255',
            '65536',
            '18446744073709551615',
            '18446744073709551616',
            '340282366920938463463374607431768211455',
            '123456789012345678901234567890123456789',
        ];

        foreach ($strings as $str) {
            $val = Uint128::fromString($str);
            $this->assertSame($str, $val->toString(), "Round-trip string: $str");
        }
    }

    public function testMaxValue(): void
    {
        $max = Uint128::fromParts(-1, -1);
        $this->assertFalse($max->isZero());
        $this->assertSame('340282366920938463463374607431768211455', $max->toString());
        $this->assertSame('ffffffffffffffffffffffffffffffff', $max->toHex());
    }

    public function testFromIntZero(): void
    {
        $this->assertTrue(Uint128::fromInt(0)->isZero());
    }

    public function testFromIntNegativeIsLargeUnsigned(): void
    {
        // -1 => 2^64-1 as unsigned
        $val = Uint128::fromInt(-1);
        $this->assertSame('18446744073709551615', $val->toString());
        $this->assertSame(-1, $val->toArray()['low']);
        $this->assertSame(0, $val->toArray()['high']);

        // Round-trip via toString
        $this->assertSame('18446744073709551615', Uint128::fromString('18446744073709551615')->toString());
    }
}
