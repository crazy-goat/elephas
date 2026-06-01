<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\AbstractBackend;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\Operation;
use PHPUnit\Framework\TestCase;

final class TestBackend extends AbstractBackend
{
    private int $submitCount = 0;

    private int $closeCount = 0;

    private ?string $lastData = null;

    private ?Operation $lastOperation = null;

    private string $submitResult = '';

    private ?int $maxSize = null;

    protected function doSubmit(Operation $operation, string $data): string
    {
        $this->submitCount++;
        $this->lastOperation = $operation;
        $this->lastData = $data;

        return $this->submitResult;
    }

    protected function doClose(): void
    {
        $this->closeCount++;
    }

    protected function getMaxBatchSize(): int
    {
        return $this->maxSize ?? parent::getMaxBatchSize();
    }

    public function setMaxBatchSize(int $size): void
    {
        $this->maxSize = $size;
    }

    public function getSubmitCount(): int
    {
        return $this->submitCount;
    }

    public function getCloseCount(): int
    {
        return $this->closeCount;
    }

    public function getLastOperation(): ?Operation
    {
        return $this->lastOperation;
    }

    public function getLastData(): ?string
    {
        return $this->lastData;
    }

    public function setSubmitResult(string $result): void
    {
        $this->submitResult = $result;
    }
}

class AbstractBackendTest extends TestCase
{
    private TestBackend $backend;

    protected function setUp(): void
    {
        $this->backend = new TestBackend();
    }

    public function testSubmitDelegatesToDoSubmit(): void
    {
        $this->backend->setSubmitResult('response-data');

        $result = $this->backend->submit(Operation::CREATE_ACCOUNTS, 'request-data');

        $this->assertSame('response-data', $result);
        $this->assertSame(Operation::CREATE_ACCOUNTS, $this->backend->getLastOperation());
        $this->assertSame('request-data', $this->backend->getLastData());
    }

    public function testSubmitReturnsCorrectResultForAllOperations(): void
    {
        $operations = [
            Operation::CREATE_ACCOUNTS,
            Operation::CREATE_TRANSFERS,
            Operation::LOOKUP_ACCOUNTS,
            Operation::LOOKUP_TRANSFERS,
            Operation::GET_ACCOUNT_TRANSFERS,
            Operation::GET_ACCOUNT_BALANCES,
        ];

        $this->backend->setSubmitResult('ok');

        foreach ($operations as $op) {
            $result = $this->backend->submit($op, 'data');
            $this->assertSame('ok', $result);
            $this->assertSame($op, $this->backend->getLastOperation());
        }

        $this->assertSame(\count($operations), $this->backend->getSubmitCount());
    }

    public function testSubmitAfterCloseThrowsClientClosedException(): void
    {
        $this->backend->close();

        $this->expectException(ClientClosedException::class);

        $this->backend->submit(Operation::CREATE_ACCOUNTS, '');
    }

    public function testCloseIsIdempotent(): void
    {
        $this->backend->close();
        $this->backend->close();

        $this->assertSame(1, $this->backend->getCloseCount());
    }

    public function testDoCloseCalledOnceOnClose(): void
    {
        $this->assertSame(0, $this->backend->getCloseCount());

        $this->backend->close();

        $this->assertSame(1, $this->backend->getCloseCount());
    }

    public function testDoCloseNotCalledOnSecondClose(): void
    {
        $this->backend->close();
        $this->backend->close();

        $this->assertSame(1, $this->backend->getCloseCount());
    }

    public function testSubmitOversizedDataThrowsTooMuchDataException(): void
    {
        $this->backend->setMaxBatchSize(10);

        $this->expectException(TooMuchDataException::class);

        $this->backend->submit(Operation::CREATE_ACCOUNTS, 'data that is too long');
    }

    public function testSubmitWithinSizeLimitSucceeds(): void
    {
        $this->backend->setMaxBatchSize(100);
        $this->backend->setSubmitResult('ok');

        $result = $this->backend->submit(Operation::CREATE_ACCOUNTS, 'small');

        $this->assertSame('ok', $result);
    }

    public function testValidatedClosedBeforeSizeCheck(): void
    {
        $this->backend->setMaxBatchSize(10);
        $this->backend->close();

        $this->expectException(ClientClosedException::class);

        $this->backend->submit(Operation::CREATE_ACCOUNTS, 'small');
    }
}
