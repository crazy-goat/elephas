<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\AccountFlags;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\CreateTransferResultBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
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

    public function testCreateTransferSameDebitCredit(): void
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

    public function testCreateTransferInvalidDebitAccount(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->assertSame(
                CreateTransferStatus::CREATED,
                $results->getResult()->getStatus(),
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

    public function testLookupTransfersSingle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId(Id::generate());

            $transfers = $client->lookupTransfers($ids);

            $this->assertSame(1, $transfers->getLength());
            $transfers->rewind();
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->assertTrue($existingId->equals($transfers->getId()));
            $this->assertTrue($transfers->getAmount()->equals(Uint128::fromInt(50)));

            $transfers->next();
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->lookupTransfers(new IdBatch(1));
    }

    public function testLookupTransfersVerifyFields(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
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
}
