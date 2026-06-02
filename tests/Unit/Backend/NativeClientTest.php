<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\NativeClient;
use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Exception\RequestException;
use CrazyGoat\Elephas\Exception\RequestTimeoutException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativeClientTest extends TestCase
{
    #[Test]
    public function constructorDetectsLibrary(): void
    {
        try {
            $client = new NativeClient();
            $this->assertInstanceOf(NativeClient::class, $client);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(InitializationException::class, $e);
            $this->assertStringContainsString('Cannot find tb_client', $e->getMessage());
        }
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
        $this->assertNotSame(0, NativeClient::PACKET_PENDING);
        $this->assertNotSame(PacketStatus::OK->value, NativeClient::PACKET_PENDING);
    }

    #[Test]
    public function defaultTimeoutIsExposed(): void
    {
        $client = $this->createClientWithoutFfi();

        $this->assertSame(NativeClient::DEFAULT_TIMEOUT_SECONDS, $client->getTimeoutSeconds());
    }

    #[Test]
    public function defaultTimeoutConstantIsPositive(): void
    {
        $this->assertGreaterThan(0.0, NativeClient::DEFAULT_TIMEOUT_SECONDS);
    }

    #[Test]
    public function processCompletionResultIncludesConfiguredTimeoutInException(): void
    {
        $client = $this->createClientWithoutFfi();
        $client->setTimeoutForTests(4.25);
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestTimeoutException::class);
        $this->expectExceptionMessage('4.250');

        $method->invoke($client, NativeClient::PACKET_PENDING, 0, '');
    }

    #[Test]
    public function processCompletionResultExposesConfiguredTimeoutOnException(): void
    {
        $client = $this->createClientWithoutFfi();
        $client->setTimeoutForTests(2.0);
        $method = $this->getProcessCompletionResultMethod();

        try {
            $method->invoke($client, NativeClient::PACKET_PENDING, 0, '');
            $this->fail('Expected RequestTimeoutException');
        } catch (RequestTimeoutException $e) {
            $this->assertSame(2.0, $e->getTimeoutSeconds());
        }
    }

    #[Test]
    public function constructorRejectsZeroTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout must be positive');

        new NativeClient('/nonexistent/libtb_client.so', 0.0);
    }

    #[Test]
    public function constructorRejectsNegativeTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout must be positive');

        new NativeClient('/nonexistent/libtb_client.so', -1.0);
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
    public function processCompletionResultThrowsRequestTimeoutExceptionForSentinelValue(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestTimeoutException::class);
        $this->expectExceptionMessage('timed out');

        $method->invoke($client, NativeClient::PACKET_PENDING, 0, '');
    }

    #[Test]
    public function initThrowsValueErrorWhenClusterIdIsWrongLength(): void
    {
        $client = $this->createClientWithoutFfi();

        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Cluster ID must be exactly 16 bytes');

        $client->init('too-short', ['127.0.0.1:3000']);
    }

    #[Test]
    public function initThrowsValueErrorWhenClusterIdIsEmpty(): void
    {
        $client = $this->createClientWithoutFfi();

        $this->expectException(\ValueError::class);

        $client->init('', ['127.0.0.1:3000']);
    }

    #[Test]
    public function initThrowsValueErrorWhenClusterIdIsTooLong(): void
    {
        $client = $this->createClientWithoutFfi();

        $this->expectException(\ValueError::class);

        $client->init(\str_repeat('x', 17), ['127.0.0.1:3000']);
    }

    #[Test]
    public function processCompletionResultThrowsForUnknownNonOkStatus(): void
    {
        $client = $this->createClientWithoutFfi();
        $method = $this->getProcessCompletionResultMethod();

        $this->expectException(RequestException::class);

        $method->invoke($client, 255, 0, '');
    }

    // ─── NativeClient lifecycle tests ───────────────────────────────────

    #[Test]
    public function initCompletesSuccessfully(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;

        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('initFailureStatusProvider')]
    public function initThrowsForFailureStatuses(int $status, string $expectedMessage): void
    {
        $client = new TestableNativeClient();
        $client->initResult = $status;

        $this->expectException(InitializationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function initFailureStatusProvider(): array
    {
        return [
            'unexpected' => [1, 'Unexpected error during initialization'],
            'out of memory' => [2, 'Out of memory during initialization'],
            'invalid address' => [3, 'Invalid cluster address'],
            'system resources' => [4, 'Insufficient system resources'],
            'network subsystem' => [5, 'Network subsystem error'],
        ];
    }

    #[Test]
    public function initFailureProducesMeaningfulMessage(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 2;

        $this->expectException(InitializationException::class);
        $this->expectExceptionMessage('Out of memory');

        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);
    }

    #[Test]
    public function submitReturnsResponseData(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitResultData = 'response-data';
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $result = $client->submit(Operation::CREATE_ACCOUNTS, 'request-data');

        $this->assertSame('response-data', $result);
    }

    #[Test]
    public function submitReturnsEmptyStringForEmptyResponse(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitResultData = '';
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $result = $client->submit(Operation::PULSE, 'data');

        $this->assertSame('', $result);
    }

    #[Test]
    #[DataProvider('submitErrorProvider')]
    public function submitThrowsForNativeErrors(int $packetStatus, string $exceptionClass): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitErrorStatus = $packetStatus;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        /** @var class-string<\Throwable> $exceptionClass */
        $this->expectException($exceptionClass);

        $client->submit(Operation::CREATE_ACCOUNTS, 'data');
    }

    /**
     * @return array<string, array{int, class-string<\Throwable>}>
     */
    public static function submitErrorProvider(): array
    {
        return [
            'too much data' => [PacketStatus::TOO_MUCH_DATA->value, TooMuchDataException::class],
            'invalid operation' => [PacketStatus::INVALID_OPERATION->value, RequestException::class],
            'invalid data size' => [PacketStatus::INVALID_DATA_SIZE->value, RequestException::class],
            'zero address' => [PacketStatus::ZERO_ADDRESS->value, RequestException::class],
            'zero cluster id' => [PacketStatus::ZERO_CLUSTER_ID->value, RequestException::class],
            'concurrency max exceeded' => [PacketStatus::CONCURRENCY_MAX_EXCEEDED->value, RequestException::class],
        ];
    }

    #[Test]
    public function submitThrowsRequestExceptionForUnknownStatus(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitErrorStatus = 255;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('255');

        $client->submit(Operation::CREATE_ACCOUNTS, 'data');
    }

    #[Test]
    public function submitThrowsRequestTimeoutWhenPacketNeverCompletes(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitShouldTimeout = true;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $this->expectException(RequestTimeoutException::class);
        $this->expectExceptionMessage('timed out');

        $client->submit(Operation::PULSE, 'data');
    }

    #[Test]
    public function deinitCallsTbClientDeinit(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $client->deinit();

        $this->assertTrue($client->deinitCalled);
    }

    #[Test]
    public function deinitIsIdempotent(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $client->deinit();
        $client->deinit();
        $client->deinit();

        $this->assertTrue($client->deinitCalled);
    }

    #[Test]
    public function deinitAfterFailedInitDoesNotCallTbClientDeinit(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 1;

        try {
            $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);
        } catch (InitializationException) {
        }

        $client->deinit();

        $this->assertFalse($client->deinitCalled);
    }

    #[Test]
    public function multipleSubmitsAllReturnData(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->submitResultData = 'response';
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        for ($i = 0; $i < 5; $i++) {
            $result = $client->submit(Operation::PULSE, 'data');
            $this->assertSame('response', $result);
        }
    }

    #[Test]
    public function deinitDoesNotPreventSubsequentInit(): void
    {
        $client = new TestableNativeClient();
        $client->initResult = 0;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3000']);

        $client->deinit();
        $this->assertTrue($client->deinitCalled);

        // Reset and init again — should not throw
        $client->deinitCalled = false;
        $client->initResult = 0;
        $client->init(\str_repeat("\0", 16), ['127.0.0.1:3001']);
    }

    #[Test]
    #[RequiresPhpExtension('ffi')]
    public function createDataBufferReturnsBufferOfCorrectSize(): void
    {
        $client = $this->createClientWithFfi();

        $method = new \ReflectionMethod(NativeClient::class, 'createDataBuffer');

        $buffer = $method->invoke($client, 'hello');

        $this->assertInstanceOf(\FFI\CData::class, $buffer);
        $this->assertSame(5, \FFI::sizeof($buffer));
    }

    #[Test]
    #[RequiresPhpExtension('ffi')]
    public function createDataBufferCopiesContentCorrectly(): void
    {
        $client = $this->createClientWithFfi();

        $method = new \ReflectionMethod(NativeClient::class, 'createDataBuffer');

        $buffer = $method->invoke($client, 'hello');

        $this->assertSame('hello', \FFI::string($buffer, 5));
    }

    /**
     * Verify that the buffer returned by createDataBuffer() remains valid
     * after a cast to uint8_t*, which is what submit() does.  FFI::cast()
     * does not extend the lifetime of the source CData — the caller must
     * keep the original buffer alive.
     */
    #[Test]
    #[RequiresPhpExtension('ffi')]
    public function bufferSurvivesCastWhenOriginalReferenceIsKept(): void
    {
        $client = $this->createClientWithFfi();

        $method = new \ReflectionMethod(NativeClient::class, 'createDataBuffer');

        // Simulate what submit() does: allocate buffer, cast to pointer,
        // keep the original alive locally.
        /** @phpstan-var \FFI\CData $dataBuffer */
        $dataBuffer = $method->invoke($client, 'persistent');
        $ptr = $client->getFfi()->cast('uint8_t*', $dataBuffer);
        $this->assertInstanceOf(\FFI\CData::class, $ptr);

        // Read-back through the pointer — this is what pollForCompletion()
        // does via FFI::string($packet->data, ...).
        $this->assertSame('persistent', \FFI::string($ptr, 10));

        // Simulate the cast-without-reference pattern: if the intermediate
        // were discarded, the pointer could dangle.
        unset($dataBuffer);
        // After unset, the CData is eligible for GC, but FFI::string()
        // on the still-alive $ptr might still work due to PHP's GC timing.
        // This test documents the expected pattern; the real fix is the
        // code structure in submit() that keeps the reference.
    }

    /**
     * Create a NativeClient whose FFI instance can allocate uint8_t[] buffers.
     * Uses a system library (libc) so the test does not require tb_client.
     */
    private function createClientWithFfi(): \CrazyGoat\Elephas\Test\Unit\Backend\TimeoutSettableNativeClient
    {
        $ffi = \FFI::cdef('typedef unsigned char uint8_t;', 'libc.so.6');

        $client = $this->createClientWithoutFfi();
        $ref = new \ReflectionProperty(NativeClient::class, 'ffi');
        $ref->setValue($client, $ffi);

        return $client;
    }

    /** @phpstan-return TimeoutSettableNativeClient */
    private function createClientWithoutFfi(): TimeoutSettableNativeClient
    {
        $client = (new \ReflectionClass(TimeoutSettableNativeClient::class))->newInstanceWithoutConstructor();

        $timeoutProperty = new \ReflectionProperty(NativeClient::class, 'timeoutSeconds');
        $timeoutProperty->setValue($client, NativeClient::DEFAULT_TIMEOUT_SECONDS);

        return $client;
    }

    private function getProcessCompletionResultMethod(): \ReflectionMethod
    {
        return new \ReflectionMethod(NativeClient::class, 'processCompletionResult');
    }
}
