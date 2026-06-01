<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\AccountFilter;
use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\ClientInterface;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\QueryFilter;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
    public function testImplementsClientInterface(): void
    {
        $client = $this->createClientWithMockBackend();

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testWithBackendCreatesClient(): void
    {
        $backend = $this->createMock(BackendInterface::class);

        $client = Client::withBackend($backend);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertTrue($client->getClusterId()->equals(Uint128::zero()));
        $this->assertSame([], $client->getReplicaAddresses());
    }

    public function testGetClusterId(): void
    {
        $client = $this->createClientWithMockBackend();

        $this->assertTrue($client->getClusterId()->equals(Uint128::zero()));
    }

    public function testGetReplicaAddressesReturnsEmptyByDefault(): void
    {
        $client = $this->createClientWithMockBackend();

        $this->assertSame([], $client->getReplicaAddresses());
    }

    public function testCloseCallsBackendClose(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())->method('close');

        $client = Client::withBackend($backend);
        $client->close();
    }

    public function testCloseIsIdempotent(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())->method('close');

        $client = Client::withBackend($backend);
        $client->close();
        $client->close();
        $client->close();
    }

    public function testCreateAccountsSubmitsBatchBytesToBackend(): void
    {
        $batch = new AccountBatch(1);
        $batch->add();
        $batch->setId(Uint128::fromInt(42));
        $batch->setLedger(1);
        $batch->setCode(1);

        $expected = $batch->toBytes();

        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::CREATE_ACCOUNTS, $expected)
            ->willReturn(\pack('PVV', 0, 0xFFFFFFFF, 0));

        $client = Client::withBackend($backend);

        $result = $client->createAccounts($batch);

        $this->assertInstanceOf(CreateAccountResultBatch::class, $result);
    }

    public function testCreateAccountsReturnsParsedResultBatch(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 100, 0xFFFFFFFF, 0),
            \pack('PVV', 200, CreateAccountStatus::EXISTS_WITH_DEBITS->value, 0),
        ]);

        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willReturn($buffer);

        $client = Client::withBackend($backend);

        $result = $client->createAccounts(new AccountBatch(2));

        $this->assertSame(2, $result->getLength());

        $result->rewind();
        $this->assertTrue($result->getResult()->isCreated());

        $result->next();
        $this->assertSame(CreateAccountStatus::EXISTS_WITH_DEBITS, $result->getResult()->getStatus());
    }

    public function testCreateAccountsEmptyBatchProducesEmptyResult(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::CREATE_ACCOUNTS, '')
            ->willReturn('');

        $client = Client::withBackend($backend);

        $result = $client->createAccounts(new AccountBatch(0));

        $this->assertSame(0, $result->getLength());
    }

    public function testCreateAccountsPropagatesBackendException(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willThrowException(new \RuntimeException('boom'));

        $client = Client::withBackend($backend);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $client->createAccounts(new AccountBatch(1));
    }

    public function testCreateAccountsAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createAccounts(new AccountBatch(1));
    }

    public function testCreateTransfersAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createTransfers(new TransferBatch(1));
    }

    public function testLookupAccountsAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->lookupAccounts(new IdBatch(1));
    }

    public function testLookupTransfersAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->lookupTransfers(new IdBatch(1));
    }

    public function testGetAccountTransfersAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->getAccountTransfers(new AccountFilter(Uint128::zero(), Uint128::zero()));
    }

    public function testGetAccountBalancesAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->getAccountBalances(new AccountFilter(Uint128::zero(), Uint128::zero()));
    }

    public function testQueryAccountsAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->queryAccounts(new QueryFilter(Uint128::zero()));
    }

    public function testQueryTransfersAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->queryTransfers(new QueryFilter(Uint128::zero()));
    }

    public function testCreateTransfersBeforeCloseStillNotImplemented(): void
    {
        $client = $this->createClientWithMockBackend();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not implemented');

        $client->createTransfers(new TransferBatch(1));
    }

    public function testIsFinal(): void
    {
        $reflection = new \ReflectionClass(Client::class);

        $this->assertTrue($reflection->isFinal());
    }

    private function createClientWithMockBackend(): Client
    {
        /** @var MockObject&BackendInterface $backend */
        $backend = $this->createMock(BackendInterface::class);

        return Client::withBackend($backend);
    }
}
