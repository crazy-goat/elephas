<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\NativeClient;
use CrazyGoat\Elephas\Exception\InitializationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativeClientTest extends TestCase
{
    #[Test]
    public function constructorThrowsWhenLibraryNotFound(): void
    {
        $this->expectException(InitializationException::class);
        $this->expectExceptionMessage('Cannot find tb_client');

        new NativeClient();
    }

    #[Test]
    public function constructorWithInvalidPathThrows(): void
    {
        $this->expectException(InitializationException::class);

        new NativeClient('/nonexistent/path/libtb_client.so');
    }
}
