<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Helper;

use CrazyGoat\Elephas\Test\Helper\PrerequisiteTrait;
use PHPUnit\Framework\TestCase;

final class PrerequisiteTraitTest extends TestCase
{
    use PrerequisiteTrait;

    public function testIsCiReturnsFalseLocally(): void
    {
        $previous = \getenv('CI');
        \putenv('CI');

        try {
            $this->assertFalse($this->isCi());
        } finally {
            if ($previous !== false) {
                \putenv('CI=' . $previous);
            }
        }
    }

    public function testIsCiReturnsTrueWhenCiIsTrue(): void
    {
        $previous = \getenv('CI');
        \putenv('CI=true');

        try {
            $this->assertTrue($this->isCi());
        } finally {
            if ($previous !== false) {
                \putenv('CI=' . $previous);
            }
        }
    }

    public function testIsCiReturnsTrueWhenCiIsOne(): void
    {
        $previous = \getenv('CI');
        \putenv('CI=1');

        try {
            $this->assertTrue($this->isCi());
        } finally {
            if ($previous !== false) {
                \putenv('CI=' . $previous);
            }
        }
    }

    public function testGetTigerBeetleAddressReturnsNullWhenNotSet(): void
    {
        $previous = \getenv('TIGERBEETLE_ADDRESS');
        \putenv('TIGERBEETLE_ADDRESS');

        try {
            $this->assertNull($this->getTigerBeetleAddress());
        } finally {
            if ($previous !== false) {
                \putenv('TIGERBEETLE_ADDRESS=' . $previous);
            }
        }
    }

    public function testGetTigerBeetleAddressReturnsNullWhenEmpty(): void
    {
        $previous = \getenv('TIGERBEETLE_ADDRESS');
        \putenv('TIGERBEETLE_ADDRESS=');

        try {
            $this->assertNull($this->getTigerBeetleAddress());
        } finally {
            if ($previous !== false) {
                \putenv('TIGERBEETLE_ADDRESS=' . $previous);
            }
        }
    }

    public function testGetTigerBeetleAddressReturnsValue(): void
    {
        $previous = \getenv('TIGERBEETLE_ADDRESS');
        \putenv('TIGERBEETLE_ADDRESS=localhost:3000');

        try {
            $this->assertSame('localhost:3000', $this->getTigerBeetleAddress());
        } finally {
            if ($previous !== false) {
                \putenv('TIGERBEETLE_ADDRESS=' . $previous);
            }
        }
    }

    public function testFailOrMarkTestSkippedSkipsLocally(): void
    {
        $previous = \getenv('CI');
        \putenv('CI');

        try {
            $this->failOrMarkTestSkipped('local skip message');
        } catch (\PHPUnit\Framework\IncompleteTestError) {
            $this->expectNotToPerformAssertions();
        } finally {
            if ($previous !== false) {
                \putenv('CI=' . $previous);
            }
        }
    }
}
