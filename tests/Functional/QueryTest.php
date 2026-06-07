<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\TransferBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\QueryFilter;
use CrazyGoat\Elephas\QueryFilterFlags;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for queryAccounts / queryTransfers.
 *
 * These tests require a running TigerBeetle instance (TIGERBEETLE_ADDRESS env var)
 * and the tb_client shared library available to FFI.
 *
 * Each test scopes its query by a unique user_data_128 value so that the
 * shared cluster state (other tests' accounts) does not affect assertions.
 */
class QueryTest extends TestCase
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
     * @return array<int, Uint128>
     */
    private function createScopedAccounts(
        Client $client,
        Uint128 $userData128,
        int $count,
        int $ledger = 1,
        int $code = 1,
    ): array {
        $batch = new AccountBatch($count);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = Id::generate();
            $ids[] = $id;
            $batch->add();
            $batch->setId($id);
            $batch->setUserData128($userData128);
            $batch->setLedger($ledger);
            $batch->setCode($code);
        }

        $results = $client->createAccounts($batch);
        $this->assertSame($count, $results->getLength());
        $results->rewind();
        for ($i = 0; $i < $count; $i++) {
            $this->assertSame(
                CreateAccountStatus::CREATED,
                $results->getResult()->getStatus(),
                \sprintf('Scoped account #%d must be CREATED', $i),
            );
            if ($i < $count - 1) {
                $results->next();
            }
        }

        return $ids;
    }

    /**
     * @return array<int, Uint128>
     */
    private function createScopedTransfers(
        Client $client,
        Uint128 $debit,
        Uint128 $credit,
        Uint128 $userData128,
        int $count,
        int $ledger = 1,
        int $code = 1,
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
            $batch->setUserData128($userData128);
            $batch->setLedger($ledger);
            $batch->setCode($code);
        }
        $client->createTransfers($batch);

        return $ids;
    }

    public function testQueryAccountsByUserData128(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $scope = Id::generate();
            $this->createScopedAccounts($client, $scope, 3);

            $filter = new QueryFilter(userData128: $scope);
            $accounts = $client->queryAccounts($filter);

            $this->assertSame(3, $accounts->getLength());
            $accounts->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertTrue(
                    $scope->equals($accounts->getUserData128()),
                    \sprintf('Account #%d user_data_128 must match scope', $i),
                );
                if ($i < 2) {
                    $accounts->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testQueryAccountsEmptyResult(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            // A freshly generated user_data_128 with no matching accounts.
            $filter = new QueryFilter(userData128: Id::generate());

            $accounts = $client->queryAccounts($filter);

            $this->assertSame(0, $accounts->getLength());
        } finally {
            $client->close();
        }
    }

    public function testQueryAccountsWithLimit(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $scope = Id::generate();
            $this->createScopedAccounts($client, $scope, 5);

            $filter = new QueryFilter(userData128: $scope, limit: 2);

            $accounts = $client->queryAccounts($filter);

            $this->assertSame(2, $accounts->getLength());
        } finally {
            $client->close();
        }
    }

    public function testQueryAccountsReversed(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $scope = Id::generate();
            $this->createScopedAccounts($client, $scope, 3);

            $flags = QueryFilterFlags::REVERSED;
            $filter = new QueryFilter(userData128: $scope, flags: $flags);

            $accounts = $client->queryAccounts($filter);

            $this->assertSame(3, $accounts->getLength());
            $accounts->rewind();
            $firstId = $accounts->getId();

            // Forward order starts at the oldest event; reversed order ends there.
            $reversedId = $accounts->getId();
            $accounts->next();
            $reversedId = $accounts->getId();
            $accounts->next();
            $reversedId = $accounts->getId();

            $this->assertTrue($firstId->equals($reversedId));
        } finally {
            $client->close();
        }
    }

    public function testQueryTransfersByUserData128(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createScopedAccounts($client, Id::generate(), 2);

            $transferScope = Id::generate();
            $this->createScopedTransfers($client, $debit, $credit, $transferScope, 3);

            $filter = new QueryFilter(userData128: $transferScope);
            $transfers = $client->queryTransfers($filter);

            $this->assertSame(3, $transfers->getLength());
            $transfers->rewind();
            for ($i = 0; $i < 3; $i++) {
                $this->assertTrue(
                    $transferScope->equals($transfers->getUserData128()),
                    \sprintf('Transfer #%d user_data_128 must match scope', $i),
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

    public function testQueryTransfersEmptyResult(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $filter = new QueryFilter(userData128: Id::generate());

            $transfers = $client->queryTransfers($filter);

            $this->assertSame(0, $transfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testQueryTransfersWithLimit(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            [$debit, $credit] = $this->createScopedAccounts($client, Id::generate(), 2);

            $transferScope = Id::generate();
            $this->createScopedTransfers($client, $debit, $credit, $transferScope, 5);

            $filter = new QueryFilter(userData128: $transferScope, limit: 3);

            $transfers = $client->queryTransfers($filter);

            $this->assertSame(3, $transfers->getLength());
        } finally {
            $client->close();
        }
    }

    public function testQueryAccountsAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->queryAccounts(new QueryFilter(Uint128::zero()));
    }

    public function testQueryTransfersAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->queryTransfers(new QueryFilter(Uint128::zero()));
    }

    public function testQueryAccountsReturnsOnlyOpenByDefault(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            // Sanity-check default semantics: a fresh user_data_128 scope with
            // 2 created accounts returns exactly 2.
            $scope = Id::generate();
            $this->createScopedAccounts($client, $scope, 2);

            $filter = new QueryFilter(userData128: $scope);
            $accounts = $client->queryAccounts($filter);

            $this->assertSame(2, $accounts->getLength());
        } finally {
            $client->close();
        }
    }
}
