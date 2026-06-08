<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\QueryFilter;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class QueryFilterTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $userData128 = Uint128::fromParts(1, 2);

        $filter = new QueryFilter(
            userData128: $userData128,
            userData64: 100,
            userData32: 200,
            ledger: 300,
            code: 400,
            timestampMin: 500,
            timestampMax: 600,
            limit: 50,
            flags: 7,
        );

        $this->assertSame($userData128, $filter->getUserData128());
        $this->assertSame(100, $filter->getUserData64());
        $this->assertSame(200, $filter->getUserData32());
        $this->assertSame(300, $filter->getLedger());
        $this->assertSame(400, $filter->getCode());
        $this->assertSame(500, $filter->getTimestampMin());
        $this->assertSame(600, $filter->getTimestampMax());
        $this->assertSame(50, $filter->getLimit());
        $this->assertSame(7, $filter->getFlags());
    }

    public function testDefaultValuesAreZero(): void
    {
        $userData128 = Uint128::zero();
        $filter = new QueryFilter($userData128);

        $this->assertSame(0, $filter->getUserData64());
        $this->assertSame(0, $filter->getUserData32());
        $this->assertSame(0, $filter->getLedger());
        $this->assertSame(0, $filter->getCode());
        $this->assertSame(0, $filter->getTimestampMin());
        $this->assertSame(0, $filter->getTimestampMax());
        $this->assertSame(0, $filter->getLimit());
        $this->assertSame(0, $filter->getFlags());
    }

    public function testIsReadonly(): void
    {
        $filter = new QueryFilter(Uint128::zero());

        $refl = new \ReflectionClass($filter);
        $this->assertTrue($refl->isReadOnly());
    }

    public function testMaxValues(): void
    {
        $userData128 = Uint128::fromString('340282366920938463463374607431768211455'); // max
        $filter = new QueryFilter(
            userData128: $userData128,
            userData64: PHP_INT_MAX,
            userData32: PHP_INT_MAX,
            ledger: PHP_INT_MAX,
            code: PHP_INT_MAX,
            timestampMin: PHP_INT_MAX,
            timestampMax: PHP_INT_MAX,
            limit: PHP_INT_MAX,
            flags: PHP_INT_MAX,
        );

        $this->assertTrue($userData128->equals($filter->getUserData128()));
        $this->assertSame(PHP_INT_MAX, $filter->getUserData64());
        $this->assertSame(PHP_INT_MAX, $filter->getUserData32());
        $this->assertSame(PHP_INT_MAX, $filter->getLedger());
        $this->assertSame(PHP_INT_MAX, $filter->getCode());
        $this->assertSame(PHP_INT_MAX, $filter->getTimestampMin());
        $this->assertSame(PHP_INT_MAX, $filter->getTimestampMax());
        $this->assertSame(PHP_INT_MAX, $filter->getLimit());
        $this->assertSame(PHP_INT_MAX, $filter->getFlags());
    }
}
