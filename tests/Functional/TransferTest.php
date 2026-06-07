<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\AccountFilterFlags;
use CrazyGoat\Elephas\AccountFlags;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\AccountFilterBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\Test\Helper\PrerequisiteTrait;
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
    use PrerequisiteTrait;

    private function createClient(): ?Client
    {
        $address = $this->getTigerBeetleAddress();
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
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Successful transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferLinked(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $previousTimestamp = 0;
            for ($i = 0; $i < 3; $i++) {
                $result = $results->getResult();
                $this->assertSame(
                    CreateTransferStatus::CREATED,
                    $result->getStatus(),
                    \sprintf('Linked transfer #%d must be CREATED', $i),
                );
                $this->assertGreaterThan(
                    $previousTimestamp,
                    $result->getTimestamp(),
                    \sprintf('Linked transfer #%d timestamp must be strictly increasing', $i),
                );
                $previousTimestamp = $result->getTimestamp();
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
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Pending transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferPostPending(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Post-pending-transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferVoidPending(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Void-pending-transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferSameDebitCredit(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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

    public function testCreateTransferInvalidDebitAccount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$credit] = $this->createAccounts($client, 1);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            // Debit account was never created.
            $batch->setDebitAccountId(Id::generate());
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(1));
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertNotSame(
                CreateTransferStatus::CREATED,
                $this->statusOrError($results),
                'Transfer with a non-existent debit account must not be CREATED',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferInvalidCreditAccount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit] = $this->createAccounts($client, 1);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            // Credit account was never created.
            $batch->setCreditAccountId(Id::generate());
            $batch->setAmount(Uint128::fromInt(1));
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertNotSame(
                CreateTransferStatus::CREATED,
                $this->statusOrError($results),
                'Transfer with a non-existent credit account must not be CREATED',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferInsufficientBalance(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            // Debit account constrained: total debits MUST NOT exceed credits.
            // With no incoming credits, any debit > 0 must be rejected.
            [$debit] = $this->createAccounts(
                $client,
                1,
                AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
            );
            [$credit] = $this->createAccounts($client, 1);

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
            $this->assertNotSame(
                CreateTransferStatus::CREATED,
                $this->statusOrError($results),
                'Transfer that would exceed the debit-side limit must not be CREATED',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferBalancingDebit(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            // BALANCING_DEBIT instructs TigerBeetle to cap the transfer amount
            // at whatever the debit side can support. With no prior balance
            // the resulting posted amount may be 0, but the request itself
            // must still be accepted as CREATED.
            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(1000));
            $batch->setLedger(1);
            $batch->setCode(1);
            $batch->setFlags(TransferFlags::BALANCING_DEBIT);

            $results = $client->createTransfers($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Balancing-debit transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferZeroAmount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Zero-amount transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferLargeAmount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $result = $results->getResult();
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $result->getStatus(),
            );
            $this->assertGreaterThan(
                0,
                $result->getTimestamp(),
                'Large-amount transfer creation must return a positive timestamp',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateTransferEmptyBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
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
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createTransfers(new TransferBatch(1));
    }

    public function testLookupTransfersSingle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $transferId = Id::generate();
            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId($transferId);
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(100));
            $batch->setLedger(1);
            $batch->setCode(1);
            $client->createTransfers($batch);

            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId($transferId);

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame(1, $transfers->getLength());
            $transfers->rewind();
            $this->assertTrue($transferId->equals($transfers->getId()));
            $this->assertTrue($debit->equals($transfers->getDebitAccountId()));
            $this->assertTrue($credit->equals($transfers->getCreditAccountId()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::fromInt(100)));
            $this->assertSame(1, $transfers->getLedger());
            $this->assertSame(1, $transfers->getCode());
        } finally {
            $client->close();
        }
    }

    public function testLookupTransfersMultiple(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $count = 3;
            $createBatch = new TransferBatch($count);
            $expectedIds = [];
            for ($i = 0; $i < $count; $i++) {
                $id = Id::generate();
                $expectedIds[] = $id;
                $createBatch->add();
                $createBatch->setId($id);
                $createBatch->setDebitAccountId($debit);
                $createBatch->setCreditAccountId($credit);
                $createBatch->setAmount(Uint128::fromInt(10));
                $createBatch->setLedger(1);
                $createBatch->setCode(1);
            }
            $client->createTransfers($createBatch);

            $ids = new IdBatch($count);
            foreach ($expectedIds as $id) {
                $ids->add();
                $ids->setId($id);
            }

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame($count, $transfers->getLength());
            $transfers->rewind();
            for ($i = 0; $i < $count; $i++) {
                $this->assertTrue(
                    $expectedIds[$i]->equals($transfers->getId()),
                    \sprintf('Transfer #%d must match expected ID', $i),
                );
                if ($i < $count - 1) {
                    $transfers->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testLookupTransfersNonExisting(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId(Id::generate());

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame(1, $transfers->getLength());
            $transfers->rewind();
            $this->assertFalse($transfers->isFound());
            $this->assertTrue($transfers->getId()->equals(Uint128::zero()));
            $this->assertTrue($transfers->getDebitAccountId()->equals(Uint128::zero()));
            $this->assertTrue($transfers->getCreditAccountId()->equals(Uint128::zero()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::zero()));
            $this->assertSame(0, $transfers->getLedger());
            $this->assertSame(0, $transfers->getCode());
            $this->assertSame(0, $transfers->getTimestamp());
        } finally {
            $client->close();
        }
    }

    public function testLookupTransfersMixed(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $existingId = Id::generate();
            $createBatch = new TransferBatch(1);
            $createBatch->add();
            $createBatch->setId($existingId);
            $createBatch->setDebitAccountId($debit);
            $createBatch->setCreditAccountId($credit);
            $createBatch->setAmount(Uint128::fromInt(50));
            $createBatch->setLedger(1);
            $createBatch->setCode(1);
            $client->createTransfers($createBatch);

            $missingId = Id::generate();
            $ids = new IdBatch(2);
            $ids->add();
            $ids->setId($existingId);
            $ids->add();
            $ids->setId($missingId);

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame(2, $transfers->getLength());

            $transfers->rewind();
            $this->assertTrue($transfers->isFound());
            $this->assertTrue($existingId->equals($transfers->getId()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::fromInt(50)));

            $transfers->next();
            $this->assertFalse($transfers->isFound());
            $this->assertTrue($transfers->getId()->equals(Uint128::zero()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::zero()));
        } finally {
            $client->close();
        }
    }

    public function testLookupTransfersEmptyBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $transfers = $client->lookupTransfers(new IdBatch(0));

            $this->assertSame(0, $transfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testLookupTransfersAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->lookupTransfers(new IdBatch(1));
    }

    public function testLookupTransfersVerifyFields(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);

            $pendingId = Id::generate();
            $pendingBatch = new TransferBatch(1);
            $pendingBatch->add();
            $pendingBatch->setId($pendingId);
            $pendingBatch->setDebitAccountId($debit);
            $pendingBatch->setCreditAccountId($credit);
            $pendingBatch->setAmount(Uint128::fromInt(200));
            $pendingBatch->setLedger(42);
            $pendingBatch->setCode(7);
            $pendingBatch->setFlags(TransferFlags::PENDING);
            $client->createTransfers($pendingBatch);

            $postId = Id::generate();
            $userData128 = Uint128::fromInt(0xCAFEBABE);
            $postBatch = new TransferBatch(1);
            $postBatch->add();
            $postBatch->setId($postId);
            $postBatch->setDebitAccountId($debit);
            $postBatch->setCreditAccountId($credit);
            $postBatch->setAmount(Uint128::fromInt(200));
            $postBatch->setPendingId($pendingId);
            $postBatch->setUserData128($userData128);
            $postBatch->setUserData64(0xDEADBEEF);
            $postBatch->setUserData32(0x12345678);
            $postBatch->setLedger(42);
            $postBatch->setCode(7);
            $postBatch->setFlags(TransferFlags::POST_PENDING_TRANSFER);
            $client->createTransfers($postBatch);

            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId($postId);

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame(1, $transfers->getLength());
            $transfers->rewind();
            $this->assertTrue($postId->equals($transfers->getId()));
            $this->assertTrue($debit->equals($transfers->getDebitAccountId()));
            $this->assertTrue($credit->equals($transfers->getCreditAccountId()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::fromInt(200)));
            $this->assertTrue($pendingId->equals($transfers->getPendingId()));
            $this->assertTrue($userData128->equals($transfers->getUserData128()));
            $this->assertSame(0xDEADBEEF, $transfers->getUserData64());
            $this->assertSame(0x12345678, $transfers->getUserData32());
            $this->assertSame(42, $transfers->getLedger());
            $this->assertSame(7, $transfers->getCode());
            $this->assertSame(TransferFlags::POST_PENDING_TRANSFER, $transfers->getFlags());
            $this->assertGreaterThan(0, $transfers->getTimestamp());
        } finally {
            $client->close();
        }
    }

    /**
     * Returns the result status, or a sentinel that is guaranteed not to be CREATED
     * if the status code returned by TigerBeetle is missing from CreateTransferStatus.
     *
     * Negative tests only check "not CREATED"; the precise error code mapping is
     * verified elsewhere as the enum is kept in sync with tb_client.h.
     */
    private function statusOrError(CreateTransferResultBatch $results): CreateTransferStatus
    {
        try {
            return $results->getResult()->getStatus();
        } catch (\ValueError) {
            return CreateTransferStatus::LINKED_EVENT_FAILED;
        }
    }

    /**
     * Helper: create N transfers from $debit to $credit and return their IDs in creation order.
     *
     * @return array<int, Uint128> transfer ids
     */
    private function createTransfersBetween(
        Client $client,
        Uint128 $debit,
        Uint128 $credit,
        int $count,
    ): array {
        $batch = new TransferBatch($count);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = Id::generate();
            $ids[] = $id;
            $batch->add();
            $batch->setId($id);
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(10));
            $batch->setLedger(1);
            $batch->setCode(1);
        }
        $client->createTransfers($batch);

        return $ids;
    }

    /**
     * Helper: build a single-entry AccountFilterBatch with the given fields.
     */
    private function buildFilter(
        Uint128 $accountId,
        int $flags = 0,
        int $timestampMin = 0,
        int $timestampMax = 0,
        int $limit = 0,
    ): AccountFilterBatch {
        $filter = new AccountFilterBatch(1);
        $filter->add();
        $filter->setAccountId($accountId);
        $filter->setFlags($flags);
        $filter->setTimestampMin($timestampMin);
        $filter->setTimestampMax($timestampMax);
        $filter->setLimit($limit);

        return $filter;
    }

    public function testGetAccountTransfersAll(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $ids = $this->createTransfersBetween($client, $debit, $credit, 3);

            $filter = $this->buildFilter(
                $debit,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $transfers = $client->getAccountTransfers($filter);

            $this->assertSame(3, $transfers->getLength());
            $transfers->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertTrue(
                    $ids[$i]->equals($transfers->getId()),
                    \sprintf('Transfer #%d must match expected ID', $i),
                );
                $this->assertTrue($debit->equals($transfers->getDebitAccountId()));
                $this->assertTrue($credit->equals($transfers->getCreditAccountId()));
                if ($i < 2) {
                    $transfers->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersDebitsOnly(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            // Querying the debit account with DEBITS-only returns all 3 transfers
            // (account_id matches debit side).
            $debitFilter = $this->buildFilter($debit, AccountFilterFlags::DEBITS);
            $debitTransfers = $client->getAccountTransfers($debitFilter);
            $this->assertSame(3, $debitTransfers->getLength());

            // Querying the credit account with DEBITS-only returns no transfers
            // (account_id never matches the debit side of any transfer).
            $creditFilter = $this->buildFilter($credit, AccountFilterFlags::DEBITS);
            $creditTransfers = $client->getAccountTransfers($creditFilter);
            $this->assertSame(0, $creditTransfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersCreditsOnly(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            // Querying the credit account with CREDITS-only returns all 3 transfers.
            $creditFilter = $this->buildFilter($credit, AccountFilterFlags::CREDITS);
            $creditTransfers = $client->getAccountTransfers($creditFilter);
            $this->assertSame(3, $creditTransfers->getLength());

            // Querying the debit account with CREDITS-only returns nothing.
            $debitFilter = $this->buildFilter($debit, AccountFilterFlags::CREDITS);
            $debitTransfers = $client->getAccountTransfers($debitFilter);
            $this->assertSame(0, $debitTransfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersReversed(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $ids = $this->createTransfersBetween($client, $debit, $credit, 3);

            $flags = AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS;

            $forward = $client->getAccountTransfers($this->buildFilter($debit, $flags));
            $this->assertSame(3, $forward->getLength());
            $forward->rewind();
            $forwardIds = [];
            for ($i = 0; $i < 3; $i++) {
                $forwardIds[] = $forward->getId();
                if ($i < 2) {
                    $forward->next();
                }
            }

            $reversed = $client->getAccountTransfers(
                $this->buildFilter($debit, $flags | AccountFilterFlags::REVERSED),
            );
            $this->assertSame(3, $reversed->getLength());
            $reversed->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertTrue(
                    $forwardIds[2 - $i]->equals($reversed->getId()),
                    \sprintf('Reversed transfer #%d must equal forward transfer #%d', $i, 2 - $i),
                );
                if ($i < 2) {
                    $reversed->next();
                }
            }

            // Sanity check: the first reversed transfer is the last forward one.
            $this->assertTrue($ids[2]->equals($forwardIds[2]));
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersTimestampRange(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            $flags = AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS;

            $all = $client->getAccountTransfers($this->buildFilter($debit, $flags));
            $this->assertSame(3, $all->getLength());
            $all->rewind();
            $timestamps = [];
            for ($i = 0; $i < 3; $i++) {
                $timestamps[] = $all->getTimestamp();
                if ($i < 2) {
                    $all->next();
                }
            }
            $this->assertGreaterThan(0, $timestamps[0]);

            // Use the middle transfer's timestamp as the inclusive lower bound;
            // expect 2 results (middle + last).
            $filtered = $client->getAccountTransfers(
                $this->buildFilter($debit, $flags, timestampMin: $timestamps[1]),
            );
            $this->assertSame(2, $filtered->getLength());

            // Use the middle transfer's timestamp as the inclusive upper bound;
            // expect 2 results (first + middle).
            $upper = $client->getAccountTransfers(
                $this->buildFilter($debit, $flags, timestampMax: $timestamps[1]),
            );
            $this->assertSame(2, $upper->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersLimit(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            $filter = $this->buildFilter(
                $debit,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
                limit: 2,
            );

            $transfers = $client->getAccountTransfers($filter);

            $this->assertSame(2, $transfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersEmpty(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$account] = $this->createAccounts($client, 1);

            $filter = $this->buildFilter(
                $account,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $transfers = $client->getAccountTransfers($filter);

            $this->assertSame(0, $transfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountTransfersAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->getAccountTransfers(new AccountFilterBatch(1));
    }

    public function testGetAccountBalancesSingle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            // HISTORY flag is required for TigerBeetle to record balance snapshots.
            [$debit, $credit] = $this->createAccounts($client, 2, AccountFlags::HISTORY);

            $batch = new TransferBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setDebitAccountId($debit);
            $batch->setCreditAccountId($credit);
            $batch->setAmount(Uint128::fromInt(100));
            $batch->setLedger(1);
            $batch->setCode(1);
            $client->createTransfers($batch);

            $filter = $this->buildFilter(
                $debit,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $balances = $client->getAccountBalances($filter);

            $this->assertSame(1, $balances->getLength());
            $balances->rewind();
            $balance = $balances->getBalance();
            $this->assertTrue($balance->getDebitsPosted()->equals(Uint128::fromInt(100)));
            $this->assertTrue($balance->getCreditsPosted()->equals(Uint128::zero()));
            $this->assertTrue($balance->getDebitsPending()->equals(Uint128::zero()));
            $this->assertTrue($balance->getCreditsPending()->equals(Uint128::zero()));
            $this->assertGreaterThan(0, $balance->getTimestamp());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesMultiple(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2, AccountFlags::HISTORY);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            $filter = $this->buildFilter(
                $debit,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $balances = $client->getAccountBalances($filter);

            $this->assertSame(3, $balances->getLength());
            $balances->rewind();
            $previousTimestamp = 0;
            for ($i = 0; $i < 3; $i++) {
                $balance = $balances->getBalance();
                // Cumulative debits_posted grows with each transfer of amount 10.
                $this->assertTrue(
                    $balance->getDebitsPosted()->equals(Uint128::fromInt(10 * ($i + 1))),
                    \sprintf('Balance #%d must have cumulative debits_posted = %d', $i, 10 * ($i + 1)),
                );
                $this->assertGreaterThan(
                    $previousTimestamp,
                    $balance->getTimestamp(),
                    \sprintf('Balance #%d timestamp must be strictly increasing', $i),
                );
                $previousTimestamp = $balance->getTimestamp();
                if ($i < 2) {
                    $balances->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesDebitsOnly(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2, AccountFlags::HISTORY);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            // Querying the debit account with DEBITS-only returns all 3 snapshots
            // (account_id matches the debit side of every transfer).
            $debitFilter = $this->buildFilter($debit, AccountFilterFlags::DEBITS);
            $debitBalances = $client->getAccountBalances($debitFilter);
            $this->assertSame(3, $debitBalances->getLength());

            // Querying the credit account with DEBITS-only returns no snapshots.
            $creditFilter = $this->buildFilter($credit, AccountFilterFlags::DEBITS);
            $creditBalances = $client->getAccountBalances($creditFilter);
            $this->assertSame(0, $creditBalances->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesCreditsOnly(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2, AccountFlags::HISTORY);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            // Querying the credit account with CREDITS-only returns all 3 snapshots.
            $creditFilter = $this->buildFilter($credit, AccountFilterFlags::CREDITS);
            $creditBalances = $client->getAccountBalances($creditFilter);
            $this->assertSame(3, $creditBalances->getLength());

            // Querying the debit account with CREDITS-only returns nothing.
            $debitFilter = $this->buildFilter($debit, AccountFilterFlags::CREDITS);
            $debitBalances = $client->getAccountBalances($debitFilter);
            $this->assertSame(0, $debitBalances->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesReversed(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createAccounts($client, 2, AccountFlags::HISTORY);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            $flags = AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS;

            $forward = $client->getAccountBalances($this->buildFilter($debit, $flags));
            $this->assertSame(3, $forward->getLength());
            $forward->rewind();
            $forwardTimestamps = [];
            for ($i = 0; $i < 3; $i++) {
                $forwardTimestamps[] = $forward->getBalance()->getTimestamp();
                if ($i < 2) {
                    $forward->next();
                }
            }

            $reversed = $client->getAccountBalances(
                $this->buildFilter($debit, $flags | AccountFilterFlags::REVERSED),
            );
            $this->assertSame(3, $reversed->getLength());
            $reversed->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertSame(
                    $forwardTimestamps[2 - $i],
                    $reversed->getBalance()->getTimestamp(),
                    \sprintf('Reversed snapshot #%d must equal forward snapshot #%d', $i, 2 - $i),
                );
                if ($i < 2) {
                    $reversed->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesEmpty(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$account] = $this->createAccounts($client, 1, AccountFlags::HISTORY);

            $filter = $this->buildFilter(
                $account,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $balances = $client->getAccountBalances($filter);

            $this->assertSame(0, $balances->getLength());
        } finally {
            $client->close();
        }
    }

    public function testGetAccountBalancesAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->getAccountBalances(new AccountFilterBatch(1));
    }

    public function testGetAccountBalancesRequiresHistoryFlag(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->failOrMarkTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            // No HISTORY flag – TigerBeetle does not retain balance snapshots
            // even when transfers affect this account.
            [$debit, $credit] = $this->createAccounts($client, 2);
            $this->createTransfersBetween($client, $debit, $credit, 3);

            $filter = $this->buildFilter(
                $debit,
                AccountFilterFlags::DEBITS | AccountFilterFlags::CREDITS,
            );

            $balances = $client->getAccountBalances($filter);

            $this->assertSame(0, $balances->getLength());
        } finally {
            $client->close();
        }
    }
}
