<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Exception\ClientEvictedException;
use CrazyGoat\Elephas\Exception\ClientReleaseException;
use CrazyGoat\Elephas\Exception\ElephasExceptionInterface;
use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Exception\IntegerOverflowException;
use CrazyGoat\Elephas\Exception\RequestException;
use CrazyGoat\Elephas\Exception\RequestTimeoutException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\Exception\UnknownStatusException;
use CrazyGoat\Elephas\InitStatus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all exception classes.
 */
#[CoversNothing]
final class ExceptionTest extends TestCase
{
    // ──────────────────────────────────────────────
    //  ElephasExceptionInterface
    // ──────────────────────────────────────────────

    #[Test]
    public function interfaceExtendsThrowable(): void
    {
        $interfaces = \class_implements(ElephasExceptionInterface::class);

        $this->assertIsArray($interfaces);
        $this->assertArrayHasKey(\Throwable::class, $interfaces);
    }

    // ──────────────────────────────────────────────
    //  IntegerOverflowException
    // ──────────────────────────────────────────────

    #[Test]
    public function integerOverflowExceptionImplementsInterface(): void
    {
        $e = new IntegerOverflowException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function integerOverflowExceptionExtendsRuntimeException(): void
    {
        $e = new IntegerOverflowException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function integerOverflowExceptionDefaultMessage(): void
    {
        $e = new IntegerOverflowException();

        $this->assertSame('', $e->getMessage());
    }

    #[Test]
    public function integerOverflowExceptionForIntOverflow(): void
    {
        $e = IntegerOverflowException::forIntOverflow('99999999999999999999');

        $this->assertInstanceOf(IntegerOverflowException::class, $e);
        $this->assertStringContainsString('99999999999999999999', $e->getMessage());
        $this->assertStringContainsString('PHP_INT_MAX', $e->getMessage());
    }

    #[Test]
    public function integerOverflowExceptionForValue(): void
    {
        $e = IntegerOverflowException::forValue('340282366920938463463374607431768211456');

        $this->assertInstanceOf(IntegerOverflowException::class, $e);
        $this->assertStringContainsString('340282366920938463463374607431768211456', $e->getMessage());
        $this->assertStringContainsString('2^128-1', $e->getMessage());
    }

    #[Test]
    public function integerOverflowExceptionForFieldRange(): void
    {
        $e = IntegerOverflowException::forFieldRange('user_data_32', 32, -1, 0, 4294967295);

        $this->assertInstanceOf(IntegerOverflowException::class, $e);
        $this->assertStringContainsString('user_data_32', $e->getMessage());
        $this->assertStringContainsString('unsigned 32-bit', $e->getMessage());
        $this->assertStringContainsString('0, 4294967295', $e->getMessage());
        $this->assertStringContainsString('-1', $e->getMessage());
    }

    #[Test]
    public function integerOverflowExceptionCatchedAsRuntimeException(): void
    {
        try {
            throw IntegerOverflowException::forIntOverflow('999');
        } catch (\RuntimeException $e) {
            $this->assertInstanceOf(IntegerOverflowException::class, $e);
        }
    }

    #[Test]
    public function integerOverflowExceptionCatchedAsInterface(): void
    {
        try {
            throw IntegerOverflowException::forIntOverflow('999');
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(IntegerOverflowException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  InitializationException
    // ──────────────────────────────────────────────

    #[Test]
    public function initializationExceptionImplementsInterface(): void
    {
        $e = new InitializationException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function initializationExceptionExtendsRuntimeException(): void
    {
        $e = new InitializationException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function initializationExceptionCreate(): void
    {
        $e = InitializationException::create();

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('initialize', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionCreateWithCustomMessage(): void
    {
        $e = InitializationException::create('Custom init error');

        $this->assertSame('Custom init error', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusUnexpected(): void
    {
        $e = InitializationException::fromStatus(InitStatus::UNEXPECTED);

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('Unexpected error', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusOutOfMemory(): void
    {
        $e = InitializationException::fromStatus(InitStatus::OUT_OF_MEMORY);

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('Out of memory', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusSystemResources(): void
    {
        $e = InitializationException::fromStatus(InitStatus::SYSTEM_RESOURCES);

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('system resources', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusNetworkSubsystem(): void
    {
        $e = InitializationException::fromStatus(InitStatus::NETWORK_SUBSYSTEM);

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('Network subsystem', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusSuccessToString(): void
    {
        // SUCCESS (0) should not normally occur as an error, but test it anyway
        $e = InitializationException::fromStatus(InitStatus::SUCCESS);
        $this->assertStringContainsString('Success', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionFromStatusInvalidAddress(): void
    {
        $e = InitializationException::fromStatus(InitStatus::INVALID_ADDRESS);

        $this->assertInstanceOf(InitializationException::class, $e);
        $this->assertStringContainsString('Invalid cluster address', $e->getMessage());
    }

    #[Test]
    public function initializationExceptionCatchedAsInterface(): void
    {
        try {
            throw InitializationException::fromStatus(InitStatus::UNEXPECTED);
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(InitializationException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  ClientClosedException
    // ──────────────────────────────────────────────

    #[Test]
    public function clientClosedExceptionImplementsInterface(): void
    {
        $e = new ClientClosedException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function clientClosedExceptionExtendsRuntimeException(): void
    {
        $e = new ClientClosedException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function clientClosedExceptionCreate(): void
    {
        $e = ClientClosedException::create();

        $this->assertInstanceOf(ClientClosedException::class, $e);
        $this->assertStringContainsString('closed', $e->getMessage());
    }

    #[Test]
    public function clientClosedExceptionCatchedAsInterface(): void
    {
        try {
            throw ClientClosedException::create();
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(ClientClosedException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  ClientEvictedException
    // ──────────────────────────────────────────────

    #[Test]
    public function clientEvictedExceptionImplementsInterface(): void
    {
        $e = new ClientEvictedException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function clientEvictedExceptionExtendsRuntimeException(): void
    {
        $e = new ClientEvictedException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function clientEvictedExceptionCreate(): void
    {
        $e = ClientEvictedException::create();

        $this->assertInstanceOf(ClientEvictedException::class, $e);
        $this->assertStringContainsString('evicted', $e->getMessage());
    }

    #[Test]
    public function clientEvictedExceptionCatchedAsInterface(): void
    {
        try {
            throw ClientEvictedException::create();
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(ClientEvictedException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  ClientReleaseException
    // ──────────────────────────────────────────────

    #[Test]
    public function clientReleaseExceptionImplementsInterface(): void
    {
        $e = new ClientReleaseException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function clientReleaseExceptionExtendsRuntimeException(): void
    {
        $e = new ClientReleaseException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function clientReleaseExceptionCreate(): void
    {
        $e = ClientReleaseException::create();

        $this->assertInstanceOf(ClientReleaseException::class, $e);
        $this->assertStringContainsString('release', $e->getMessage());
    }

    #[Test]
    public function clientReleaseExceptionCreateWithCustomMessage(): void
    {
        $e = ClientReleaseException::create('Custom release error');

        $this->assertSame('Custom release error', $e->getMessage());
    }

    #[Test]
    public function clientReleaseExceptionCatchedAsInterface(): void
    {
        try {
            throw ClientReleaseException::create();
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(ClientReleaseException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  TooMuchDataException
    // ──────────────────────────────────────────────

    #[Test]
    public function tooMuchDataExceptionImplementsInterface(): void
    {
        $e = new TooMuchDataException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function tooMuchDataExceptionExtendsRuntimeException(): void
    {
        $e = new TooMuchDataException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function tooMuchDataExceptionCreate(): void
    {
        $e = TooMuchDataException::create(1024, 2048);

        $this->assertInstanceOf(TooMuchDataException::class, $e);
        $this->assertStringContainsString('2048', $e->getMessage());
        $this->assertStringContainsString('1024', $e->getMessage());
    }

    #[Test]
    public function tooMuchDataExceptionCatchedAsInterface(): void
    {
        try {
            throw TooMuchDataException::create(100, 200);
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(TooMuchDataException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  RequestException
    // ──────────────────────────────────────────────

    #[Test]
    public function requestExceptionImplementsInterface(): void
    {
        $e = new RequestException();

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function requestExceptionExtendsRuntimeException(): void
    {
        $e = new RequestException();

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function requestExceptionCreate(): void
    {
        $e = RequestException::create(42);

        $this->assertInstanceOf(RequestException::class, $e);
        $this->assertStringContainsString('42', $e->getMessage());
    }

    #[Test]
    public function requestExceptionCreateWithCustomMessage(): void
    {
        $e = RequestException::create(1, 'Custom request error');

        $this->assertSame('Custom request error', $e->getMessage());
    }

    #[Test]
    public function requestExceptionCatchedAsInterface(): void
    {
        try {
            throw RequestException::create(99);
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(RequestException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  RequestTimeoutException
    // ──────────────────────────────────────────────

    #[Test]
    public function requestTimeoutExceptionImplementsInterface(): void
    {
        $e = RequestTimeoutException::create(5.0);

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function requestTimeoutExceptionExtendsRuntimeException(): void
    {
        $e = RequestTimeoutException::create(5.0);

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function requestTimeoutExceptionMessageIncludesTimeout(): void
    {
        $e = RequestTimeoutException::create(7.5);

        $this->assertStringContainsString('7.500', $e->getMessage());
        $this->assertStringContainsString('timed out', $e->getMessage());
    }

    #[Test]
    public function requestTimeoutExceptionExposesConfiguredTimeout(): void
    {
        $e = RequestTimeoutException::create(2.5);

        $this->assertSame(2.5, $e->getTimeoutSeconds());
    }

    #[Test]
    public function requestTimeoutExceptionCatchedAsInterface(): void
    {
        try {
            throw RequestTimeoutException::create(1.0);
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(RequestTimeoutException::class, $e);
            $this->assertSame(1.0, $e->getTimeoutSeconds());
        }
    }

    // ──────────────────────────────────────────────
    //  UnknownStatusException
    // ──────────────────────────────────────────────

    #[Test]
    public function unknownStatusExceptionImplementsInterface(): void
    {
        $e = UnknownStatusException::forEnum(CreateAccountStatus::class, 999);

        $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
    }

    #[Test]
    public function unknownStatusExceptionExtendsRuntimeException(): void
    {
        $e = UnknownStatusException::forEnum(CreateAccountStatus::class, 999);

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function unknownStatusExceptionMessageIncludesClassAndValue(): void
    {
        $e = UnknownStatusException::forEnum(CreateAccountStatus::class, 42);

        $this->assertStringContainsString('CreateAccountStatus', $e->getMessage());
        $this->assertStringContainsString('42', $e->getMessage());
    }

    #[Test]
    public function unknownStatusExceptionMessageIncludesKnownValues(): void
    {
        $e = UnknownStatusException::forEnum(CreateAccountStatus::class, 999);

        $this->assertStringContainsString('Known values:', $e->getMessage());
        // Should list at least some of the known enum values
        $this->assertStringContainsString('4294967295', $e->getMessage()); // CREATED = 0xFFFFFFFF
    }

    #[Test]
    public function unknownStatusExceptionForCreateTransferStatus(): void
    {
        $e = UnknownStatusException::forEnum(CreateTransferStatus::class, 999);

        $this->assertStringContainsString('CreateTransferStatus', $e->getMessage());
        $this->assertStringContainsString('999', $e->getMessage());
        $this->assertStringContainsString('Known values:', $e->getMessage());
    }

    #[Test]
    public function unknownStatusExceptionCatchedAsInterface(): void
    {
        try {
            throw UnknownStatusException::forEnum(CreateAccountStatus::class, 42);
        } catch (ElephasExceptionInterface $e) {
            $this->assertInstanceOf(UnknownStatusException::class, $e);
        }
    }

    #[Test]
    public function unknownStatusExceptionCatchedAsRuntimeException(): void
    {
        try {
            throw UnknownStatusException::forEnum(CreateAccountStatus::class, 42);
        } catch (\RuntimeException $e) {
            $this->assertInstanceOf(UnknownStatusException::class, $e);
        }
    }

    // ──────────────────────────────────────────────
    //  All exception classes are final
    // ──────────────────────────────────────────────

    #[Test]
    public function allExceptionClassesAreFinal(): void
    {
        $exceptionClasses = [
            ClientClosedException::class,
            ClientEvictedException::class,
            ClientReleaseException::class,
            InitializationException::class,
            IntegerOverflowException::class,
            RequestException::class,
            RequestTimeoutException::class,
            TooMuchDataException::class,
            UnknownStatusException::class,
        ];

        foreach ($exceptionClasses as $class) {
            $ref = new \ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), \sprintf('%s must be final', $class));
        }
    }

    // ──────────────────────────────────────────────
    //  All exceptions extend RuntimeException
    // ──────────────────────────────────────────────

    #[Test]
    public function allExceptionsExtendRuntimeException(): void
    {
        $exceptionClasses = [
            ClientClosedException::class,
            ClientEvictedException::class,
            ClientReleaseException::class,
            InitializationException::class,
            IntegerOverflowException::class,
            RequestException::class,
            RequestTimeoutException::class,
            TooMuchDataException::class,
            UnknownStatusException::class,
        ];

        foreach ($exceptionClasses as $class) {
            $e = match ($class) {
                RequestTimeoutException::class => $class::create(1.0),
                UnknownStatusException::class => $class::forEnum(CreateAccountStatus::class, 42),
                default => new $class(),
            };
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertInstanceOf(ElephasExceptionInterface::class, $e);
        }
    }
}
