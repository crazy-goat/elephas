<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\AccountFilter;
use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\CreateAccountResultBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\ClientInterface;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Internal\BinaryHelper;
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

    public function testCreateTransfersSubmitsBatchBytesToBackend(): void
    {
        $batch = new TransferBatch(1);
        $batch->add();
        $batch->setId(Uint128::fromInt(99));
        $batch->setDebitAccountId(Uint128::fromInt(1));
        $batch->setCreditAccountId(Uint128::fromInt(2));
        $batch->setAmount(Uint128::fromInt(10));
        $batch->setLedger(1);
        $batch->setCode(1);

        $expected = $batch->toBytes();

        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::CREATE_TRANSFERS, $expected)
            ->willReturn(\pack('PVV', 0, 0xFFFFFFFF, 0));

        $client = Client::withBackend($backend);

        $result = $client->createTransfers($batch);

        $this->assertInstanceOf(CreateTransferResultBatch::class, $result);
    }

    public function testCreateTransfersReturnsParsedResultBatch(): void
    {
        $buffer = \implode('', [
            \pack('PVV', 100, 0xFFFFFFFF, 0),
            \pack('PVV', 200, CreateTransferStatus::EXCEEDS_CREDITS->value, 0),
        ]);

        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willReturn($buffer);

        $client = Client::withBackend($backend);

        $result = $client->createTransfers(new TransferBatch(2));

        $this->assertSame(2, $result->getLength());

        $result->rewind();
        $this->assertTrue($result->getResult()->isCreated());

        $result->next();
        $this->assertSame(CreateTransferStatus::EXCEEDS_CREDITS, $result->getResult()->getStatus());
    }

    public function testCreateTransfersEmptyBatchProducesEmptyResult(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::CREATE_TRANSFERS, '')
            ->willReturn('');

        $client = Client::withBackend($backend);

        $result = $client->createTransfers(new TransferBatch(0));

        $this->assertSame(0, $result->getLength());
    }

    public function testCreateTransfersPropagatesBackendException(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willThrowException(new \RuntimeException('boom'));

        $client = Client::withBackend($backend);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $client->createTransfers(new TransferBatch(1));
    }

    public function testCreateTransfersAfterCloseThrowsClientClosedException(): void
    {
        $client = $this->createClientWithMockBackend();
        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createTransfers(new TransferBatch(1));
    }

    public function testLookupAccountsSubmitsIdBatchBytesToBackend(): void
    {
        $ids = new IdBatch(2);
        $ids->add();
        $ids->setId(Uint128::fromInt(11));
        $ids->add();
        $ids->setId(Uint128::fromInt(22));

        $expected = $ids->toBytes();

        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::LOOKUP_ACCOUNTS, $expected)
            ->willReturn('');

        $client = Client::withBackend($backend);

        $result = $client->lookupAccounts($ids);

        $this->assertInstanceOf(AccountBatch::class, $result);
    }

    public function testLookupAccountsReturnsParsedAccountBatch(): void
    {
        $buffer = \implode('', [
            $this->packAccount(11, 1, 1),
            $this->packAccount(22, 2, 7),
        ]);

        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willReturn($buffer);

        $client = Client::withBackend($backend);

        $result = $client->lookupAccounts(new IdBatch(2));

        $this->assertSame(2, $result->getLength());

        $result->rewind();
        $this->assertTrue($result->getId()->equals(Uint128::fromInt(11)));
        $this->assertSame(1, $result->getLedger());
        $this->assertSame(1, $result->getCode());

        $result->next();
        $this->assertTrue($result->getId()->equals(Uint128::fromInt(22)));
        $this->assertSame(2, $result->getLedger());
        $this->assertSame(7, $result->getCode());
    }

    public function testLookupAccountsReturnsZeroedAccountForMissingId(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willReturn(\str_repeat("\0", 128));

        $client = Client::withBackend($backend);

        $result = $client->lookupAccounts(new IdBatch(1));

        $this->assertSame(1, $result->getLength());
        $result->rewind();
        $this->assertTrue($result->getId()->equals(Uint128::zero()));
        $this->assertSame(0, $result->getLedger());
        $this->assertSame(0, $result->getCode());
        $this->assertSame(0, $result->getFlags());
        $this->assertTrue($result->getDebitsPosted()->equals(Uint128::zero()));
        $this->assertTrue($result->getCreditsPosted()->equals(Uint128::zero()));
    }

    public function testLookupAccountsEmptyBatchProducesEmptyResult(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())
            ->method('submit')
            ->with(Operation::LOOKUP_ACCOUNTS, '')
            ->willReturn('');

        $client = Client::withBackend($backend);

        $result = $client->lookupAccounts(new IdBatch(0));

        $this->assertSame(0, $result->getLength());
    }

    public function testLookupAccountsPropagatesBackendException(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->method('submit')->willThrowException(new \RuntimeException('boom'));

        $client = Client::withBackend($backend);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $client->lookupAccounts(new IdBatch(1));
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

    private function packAccount(int $id, int $ledger, int $code): string
    {
        return BinaryHelper::packAccount([
            'id' => Uint128::fromInt($id),
            'debits_pending' => Uint128::zero(),
            'debits_posted' => Uint128::zero(),
            'credits_pending' => Uint128::zero(),
            'credits_posted' => Uint128::zero(),
            'user_data_128' => Uint128::zero(),
            'user_data_64' => 0,
            'user_data_32' => 0,
            'reserved' => 0,
            'ledger' => $ledger,
            'code' => $code,
            'flags' => 0,
            'timestamp' => 0,
        ]);
    }
}
