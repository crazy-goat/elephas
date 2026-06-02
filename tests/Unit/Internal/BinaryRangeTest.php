<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Internal;

use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Internal\BinaryRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryRange::class)]
final class BinaryRangeTest extends TestCase
{
    // ──────────────────────────────────────────────
    //  assertUint16
    // ──────────────────────────────────────────────

    public function testAssertUint16AcceptsZero(): void
    {
        BinaryRange::assertUint16(0, 'code');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint16AcceptsMax(): void
    {
        BinaryRange::assertUint16(0xFFFF, 'flags');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint16RejectsNegative(): void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage('Field "flags" value -1 is outside the unsigned 16-bit range [0, 65535]');

        BinaryRange::assertUint16(-1, 'flags');
    }

    public function testAssertUint16RejectsOverflow(): void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage('Field "flags" value 65536 is outside the unsigned 16-bit range [0, 65535]');

        BinaryRange::assertUint16(65536, 'flags');
    }

    // ──────────────────────────────────────────────
    //  assertUint32
    // ──────────────────────────────────────────────

    public function testAssertUint32AcceptsZero(): void
    {
        BinaryRange::assertUint32(0, 'ledger');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint32AcceptsMax(): void
    {
        BinaryRange::assertUint32(0xFFFFFFFF, 'user_data_32');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint32RejectsNegative(): void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(
            'Field "ledger" value -1 is outside the unsigned 32-bit range [0, 4294967295]',
        );

        BinaryRange::assertUint32(-1, 'ledger');
    }

    public function testAssertUint32RejectsOverflow(): void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(
            'Field "user_data_32" value 4294967296 is outside the unsigned 32-bit range [0, 4294967295]',
        );

        BinaryRange::assertUint32(4294967296, 'user_data_32');
    }

    // ──────────────────────────────────────────────
    //  assertUint64
    // ──────────────────────────────────────────────

    public function testAssertUint64AcceptsZero(): void
    {
        BinaryRange::assertUint64(0, 'user_data_64');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint64AcceptsPhpIntMax(): void
    {
        BinaryRange::assertUint64(PHP_INT_MAX, 'user_data_64');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertUint64RejectsNegative(): void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage(
            \sprintf('Field "user_data_64" value -1 is outside the unsigned 64-bit range [0, %d]', PHP_INT_MAX),
        );

        BinaryRange::assertUint64(-1, 'user_data_64');
    }

    public function testAssertUint64RejectsPhpIntMin(): void
    {
        $this->expectException(IntegerOverflowException::class);

        BinaryRange::assertUint64(PHP_INT_MIN, 'user_data_64');
    }
}
