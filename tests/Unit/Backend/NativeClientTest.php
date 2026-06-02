<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\NativeClient;
use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Exception\RequestException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\PacketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    public function sentinelConstantIsDistinctFromPacketOk(): void
    {
        $constants = (new \ReflectionClass(NativeClient::class))->getReflectionConstants();
        $pending = null;
        foreach ($constants as $c) {
            if ($c->getName() === 'PACKET_PENDING') {
                $pending = $c->getValue();
                break;
            }
        }

        $this->assertNotNull($pending, 'PACKET_PENDING constant must exist');
        $this->assertNotSame(0, $pending);
        $this->assertNotSame(PacketStatus::OK->value, $pending);
    }

    #[Test]
    public function processCompletionResultReturnsDataOnOk(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $result = $method->invoke($client, PacketStatus::OK->value, 5, 'hello');

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function processCompletionResultReturnsEmptyStringForZeroSize(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $result = $method->invoke($client, PacketStatus::OK->value, 0, '');

        $this->assertSame('', $result);
    }

    #[Test]
    public function processCompletionResultThrowsForTooMuchData(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(TooMuchDataException::class);

        $method->invoke($client, PacketStatus::TOO_MUCH_DATA->value, 999999, '');
    }

    #[Test]
    #[DataProvider('requestErrorProvider')]
    public function processCompletionResultThrowsRequestExceptionForNativeErrors(int $statusCode): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestException::class);

        $method->invoke($client, $statusCode, 0, '');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function requestErrorProvider(): array
    {
        return [
            'invalid operation' => [PacketStatus::INVALID_OPERATION->value],
            'invalid data size' => [PacketStatus::INVALID_DATA_SIZE->value],
            'zero address' => [PacketStatus::ZERO_ADDRESS->value],
            'zero cluster id' => [PacketStatus::ZERO_CLUSTER_ID->value],
            'concurrency max exceeded' => [PacketStatus::CONCURRENCY_MAX_EXCEEDED->value],
        ];
    }

    #[Test]
    public function processCompletionResultIncludesMeaningfulMessageForUnknownStatus(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('99');

        $method->invoke($client, 99, 0, '');
    }

    #[Test]
    public function processCompletionResultThrowsRuntimeExceptionForSentinelValue(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $constants = (new \ReflectionClass(NativeClient::class))->getReflectionConstants();
        $sentinel = null;
        foreach ($constants as $c) {
            if ($c->getName() === 'PACKET_PENDING') {
                $sentinel = $c->getValue();
                break;
            }
        }
        $this->assertNotNull($sentinel);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        $method->invoke($client, $sentinel, 0, '');
    }

    #[Test]
    public function processCompletionResultThrowsForUnknownNonOkStatus(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestException::class);

        $method->invoke($client, 255, 0, '');
    }

    /** @phpstan-return NativeClient */
    private function createClientWithoutFfi(): NativeClient
    {
        /** @phpstan-var NativeClient $client */
        $client = (new \ReflectionClass(NativeClient::class))->newInstanceWithoutConstructor();

        return $client;
    }

    private function getProcessCompletionResultMethod(): \ReflectionMethod
    {
        return new \ReflectionMethod(NativeClient::class, 'processCompletionResult');
    }
}
