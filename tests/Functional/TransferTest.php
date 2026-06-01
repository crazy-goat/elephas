<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\AccountFlags;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\TransferFlags;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for transfer operations.
 *
 * These tests require a running TigerBeetle instance (TIGERBEETLE_ADDRESS env var)
 * and the tb_client shared library available to FFI.
 */
class TransferTest extends TestCase
{
    private function tigerBeetleAddress(): ?string
    {
        $address = \getenv('TIGERBEETLE_ADDRESS');
        if (!\is_string($address) || $address === '') {
            return null;
        }

        return $address;
    }

    private function isFfiBackendAvailable(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        try {
            new FfiBackend(Uint128::zero(), ['127.0.0.1:1']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function createClient(): ?Client
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            return null;
        }

        if (!$this->isFfiBackendAvailable()) {
            return null;
        }

        try {
            return new Client(Uint128::zero(), $address);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Helper: create N accounts on the same ledger/code, optional flags.
     *
     * @return array<int, Uint128> account ids
     */
    private function createAccounts(Client $client, int $count, int $flags = AccountFlags::NONE): array
    {
        $batch = new AccountBatch($count);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = Id::generate();
            $ids[] = $id;
            $batch->add();
            $batch->setId($id);
            $batch->setLedger(1);
            $batch->setCode(1);
            $batch->setFlags($flags);
        }
        $client->createAccounts($batch);

        return $ids;
    }

    public function testCreateTransferSimple(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(100));
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferLinked(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $batch = new TransferBatch(3);
            for ($i = 0; $i < 3; $i++) {
                $batch->add();
                $batch->setId(Id::generate());
                $batch->setDebitAccountId($debit);
                $batch->setCreditAccountId($credit);
                $batch->setAmount(Uint128::fromInt(10));
                $batch->setLedger(1);
                $batch->setCode(1);
                // Link first two; last one closes the chain (no LINKED flag).
                $batch->setFlags($i < 2 ? TransferFlags::LINKED : TransferFlags::NONE);
            }

            $results = $client->createTransfers($batch);

            $this->assertSame(3, $results->getLength());
            $results->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertSame(
                    CreateTransferStatus::CREATED,
                    $results->getResult()->getStatus(),
                    \sprintf('Linked transfer #%d must be CREATED', $i),
                );
                if ($i < 2) {
                    $results->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferPending(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(50));
            $batch->setLedger(1);
            $batch->setCode(1);
            $batch->setFlags(TransferFlags::PENDING);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferPostPending(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $pendingId = Id::generate();
            $pending = new TransferBatch(1);
            $pending->add();
            $pending->setId($pendingId);
            $pending->setDebitAccountId($debit);
            $pending->setCreditAccountId($credit);
            $pending->setAmount(Uint128::fromInt(75));
            $pending->setLedger(1);
            $pending->setCode(1);
            $pending->setFlags(TransferFlags::PENDING);
            $client->createTransfers($pending);

            $post = new TransferBatch(1);
            $post->add();
            $post->setId(Id::generate());
            $post->setDebitAccountId($debit);
            $post->setCreditAccountId($credit);
            $post->setAmount(Uint128::fromInt(75));
            $post->setPendingId($pendingId);
            $post->setLedger(1);
            $post->setCode(1);
            $post->setFlags(TransferFlags::POST_PENDING_TRANSFER);

            $results = $client->createTransfers($post);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferVoidPending(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $pendingId = Id::generate();
            $pending = new TransferBatch(1);
            $pending->add();
            $pending->setId($pendingId);
            $pending->setDebitAccountId($debit);
            $pending->setCreditAccountId($credit);
            $pending->setAmount(Uint128::fromInt(25));
            $pending->setLedger(1);
            $pending->setCode(1);
            $pending->setFlags(TransferFlags::PENDING);
            $client->createTransfers($pending);

            $void = new TransferBatch(1);
            $void->add();
            $void->setId(Id::generate());
            $void->setDebitAccountId($debit);
            $void->setCreditAccountId($credit);
            $void->setAmount(Uint128::fromInt(25));
            $void->setPendingId($pendingId);
            $void->setLedger(1);
            $void->setCode(1);
            $void->setFlags(TransferFlags::VOID_PENDING_TRANSFER);

            $results = $client->createTransfers($void);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferSameDebitCreditReturnsError(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$account] = $this->createAccounts($client, 1);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($account);
            $batch->setCreditAccountId($account);
            $batch->setAmount(Uint128::fromInt(1));
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertNotSame(
                CreateTransferStatus::CREATED,
                $this->statusOrError($results),
                'Transfer with identical debit and credit accounts must not be CREATED',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferZeroAmount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts(
                $client,
                2,
                AccountFlags::ZERO_VALUE_TRANSFERS,
            );

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::zero());
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferLargeAmount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            // Amount > PHP_INT_MAX – uses the high 64-bit half of the 128-bit amount.
            $amount = Uint128::fromParts(0, 1);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount($amount);
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferEmptyBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $results = $client->createTransfers(new TransferBatch(0));

            $this->assertSame(0, $results->getLength());
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createTransfers(new TransferBatch(1));
    }

    /**
     * Returns the result status, or a sentinel that is guaranteed not to be CREATED
     * if the status code returned by TigerBeetle is missing from CreateTransferStatus.
     *
     * Negative tests only check "not CREATED"; the precise error code mapping is
     * verified elsewhere as the enum is kept in sync with tb_client.h.
     */
    private function statusOrError(\CrazyGoat\Elephas\Batch\CreateTransferResultBatch $results): CreateTransferStatus
    {
        try {
            return $results->getResult()->getStatus();
        } catch (\ValueError) {
            return CreateTransferStatus::LINKED_EVENT_FAILED;
        }
    }
}
