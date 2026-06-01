<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Functional;

use CrazyGoat\Elephas\Backend\BackendInterface;
use CrazyGoat\Elephas\Backend\FfiBackend;
use CrazyGoat\Elephas\Batch\AccountBatch;
use CrazyGoat\Elephas\Batch\IdBatch;
use CrazyGoat\Elephas\Client;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Exception\InitializationException;
use CrazyGoat\Elephas\Id;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for Client class.
 *
 * These tests require a running TigerBeetle instance (TIGERBEETLE_ADDRESS env var).
 */
class ClientTest extends TestCase
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

    public function testConstructConnectsToTigerBeetle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $this->assertSame(0, $client->getClusterId()->toInt());
        $this->assertCount(1, $client->getReplicaAddresses());

        $client->close();
    }

    public function testConstructInvalidAddressThrows(): void
    {
        $this->expectException(InitializationException::class);

        new Client(Uint128::zero(), '999.999.999.999:9999');
    }

    public function testConstructWithMultipleReplicaAddresses(): void
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiBackendAvailable()) {
            $this->markTestSkipped('FfiBackend not available (tb_client library missing)');
        }

        $client = new Client(Uint128::zero(), $address, $address, $address);

        $this->assertCount(3, $client->getReplicaAddresses());

        $client->close();
    }

    public function testCloseReleasesResources(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testCloseIsIdempotent(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();
        $client->close();
        $client->close();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    public function testWithBackendCreatesClient(): void
    {
        $address = $this->tigerBeetleAddress();
        if ($address === null) {
            $this->markTestSkipped('TIGERBEETLE_ADDRESS env var is not set');
        }

        if (!$this->isFfiBackendAvailable()) {
            $this->markTestSkipped('FfiBackend not available (tb_client library missing)');
        }

        $backend = new FfiBackend(Uint128::zero(), [$address]);
        $client = Client::withBackend($backend);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertTrue($client->getClusterId()->equals(Uint128::zero()));
        $this->assertSame([], $client->getReplicaAddresses());

        $client->close();
    }

    public function testWithBackendUsesProvidedBackend(): void
    {
        $backend = $this->createMock(BackendInterface::class);
        $backend->expects($this->once())->method('close');

        $client = Client::withBackend($backend);
        $client->close();
    }

    public function testCreateAccountsSingle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $batch = new AccountBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setLedger(1);
            $batch->setCode(1);

            $results = $client->createAccounts($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateAccountStatus::CREATED,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsMultiple(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $count = 5;
            $batch = new AccountBatch($count);
            for ($i = 0; $i < $count; $i++) {
                $batch->add();
                $batch->setId(Id::generate());
                $batch->setLedger(1);
                $batch->setCode(1);
            }

            $results = $client->createAccounts($batch);

            $this->assertSame($count, $results->getLength());
            $results->rewind();
            for ($i = 0; $i < $count; $i++) {
                $this->assertSame(
                    CreateAccountStatus::CREATED,
                    $results->getResult()->getStatus(),
                    \sprintf('Account #%d must be CREATED', $i),
                );
                if ($i < $count - 1) {
                    $results->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsDuplicateId(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $id = Id::generate();

            $first = new AccountBatch(1);
            $first->add();
            $first->setId($id);
            $first->setLedger(1);
            $first->setCode(1);
            $client->createAccounts($first);

            $second = new AccountBatch(1);
            $second->add();
            $second->setId($id);
            $second->setLedger(1);
            $second->setCode(1);

            $results = $client->createAccounts($second);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertNotSame(
                CreateAccountStatus::CREATED,
                $results->getResult()->getStatus(),
                'Re-creating the same account ID must not return CREATED',
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsInvalidFlags(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $batch = new AccountBatch(1);
            $batch->add();
            $batch->setId(Id::generate());
            $batch->setLedger(1);
            $batch->setCode(1);
            // DEBITS_MUST_NOT_EXCEED_CREDITS | CREDITS_MUST_NOT_EXCEED_DEBITS
            // are mutually exclusive – TigerBeetle must reject this.
            $batch->setFlags((1 << 1) | (1 << 2));

            $results = $client->createAccounts($batch);

            $this->assertSame(1, $results->getLength());
            $results->rewind();
            $this->assertSame(
                CreateAccountStatus::MUTUALLY_EXCLUSIVE_FLAGS,
                $results->getResult()->getStatus(),
            );
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsEmptyBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $results = $client->createAccounts(new AccountBatch(0));

            $this->assertSame(0, $results->getLength());
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsLargeBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $count = 100;
            $batch = new AccountBatch($count);
            for ($i = 0; $i < $count; $i++) {
                $batch->add();
                $batch->setId(Id::generate());
                $batch->setLedger(1);
                $batch->setCode(1);
            }

            $results = $client->createAccounts($batch);

            $this->assertSame($count, $results->getLength());
            $results->rewind();
            for ($i = 0; $i < $count; $i++) {
                $this->assertSame(
                    CreateAccountStatus::CREATED,
                    $results->getResult()->getStatus(),
                    \sprintf('Account #%d must be CREATED', $i),
                );
                if ($i < $count - 1) {
                    $results->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testCreateAccountsAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->createAccounts(new AccountBatch(1));
    }

    public function testLookupAccountsSingle(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $id = Id::generate();
            $batch = new AccountBatch(1);
            $batch->add();
            $batch->setId($id);
            $batch->setLedger(1);
            $batch->setCode(1);
            $client->createAccounts($batch);

            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId($id);

            $accounts = $client->lookupAccounts($ids);

            $this->assertSame(1, $accounts->getLength());
            $accounts->rewind();
            $this->assertTrue($id->equals($accounts->getId()));
            $this->assertSame(1, $accounts->getLedger());
            $this->assertSame(1, $accounts->getCode());
        } finally {
            $client->close();
        }
    }

    public function testLookupAccountsMultiple(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $count = 3;
            $createBatch = new AccountBatch($count);
            $expectedIds = [];
            for ($i = 0; $i < $count; $i++) {
                $id = Id::generate();
                $expectedIds[] = $id;
                $createBatch->add();
                $createBatch->setId($id);
                $createBatch->setLedger(1);
                $createBatch->setCode(1);
            }
            $client->createAccounts($createBatch);

            $ids = new IdBatch($count);
            foreach ($expectedIds as $id) {
                $ids->add();
                $ids->setId($id);
            }

            $accounts = $client->lookupAccounts($ids);

            $this->assertSame($count, $accounts->getLength());
            $accounts->rewind();
            for ($i = 0; $i < $count; $i++) {
                $this->assertTrue(
                    $expectedIds[$i]->equals($accounts->getId()),
                    \sprintf('Account #%d must match expected ID', $i),
                );
                if ($i < $count - 1) {
                    $accounts->next();
                }
            }
        } finally {
            $client->close();
        }
    }

    public function testLookupAccountsNonExisting(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId(Id::generate());

            $accounts = $client->lookupAccounts($ids);

            $this->assertSame(1, $accounts->getLength());
            $accounts->rewind();
            $this->assertTrue($accounts->getId()->equals(Uint128::zero()));
            $this->assertSame(0, $accounts->getLedger());
            $this->assertSame(0, $accounts->getCode());
            $this->assertSame(0, $accounts->getTimestamp());
            $this->assertTrue($accounts->getDebitsPosted()->equals(Uint128::zero()));
            $this->assertTrue($accounts->getCreditsPosted()->equals(Uint128::zero()));
        } finally {
            $client->close();
        }
    }

    public function testLookupAccountsMixed(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $existingId = Id::generate();
            $createBatch = new AccountBatch(1);
            $createBatch->add();
            $createBatch->setId($existingId);
            $createBatch->setLedger(1);
            $createBatch->setCode(1);
            $client->createAccounts($createBatch);

            $missingId = Id::generate();
            $ids = new IdBatch(2);
            $ids->add();
            $ids->setId($existingId);
            $ids->add();
            $ids->setId($missingId);

            $accounts = $client->lookupAccounts($ids);

            $this->assertSame(2, $accounts->getLength());

            $accounts->rewind();
            $this->assertTrue($existingId->equals($accounts->getId()));
            $this->assertSame(1, $accounts->getLedger());

            $accounts->next();
            $this->assertTrue($accounts->getId()->equals(Uint128::zero()));
            $this->assertSame(0, $accounts->getLedger());
        } finally {
            $client->close();
        }
    }

    public function testLookupAccountsEmptyBatch(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $accounts = $client->lookupAccounts(new IdBatch(0));

            $this->assertSame(0, $accounts->getLength());
        } finally {
            $client->close();
        }
    }

    public function testLookupAccountsAfterCloseThrows(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        $client->close();

        $this->expectException(ClientClosedException::class);

        $client->lookupAccounts(new IdBatch(1));
    }

    public function testLookupAccountsVerifyFields(): void
    {
        $client = $this->createClient();
        if (!$client instanceof Client) {
            $this->markTestSkipped('TigerBeetle or FFI not available');
        }

        try {
            $id = Id::generate();
            $userData128 = Uint128::fromInt(0xCAFEBABE);
            $createBatch = new AccountBatch(1);
            $createBatch->add();
            $createBatch->setId($id);
            $createBatch->setUserData128($userData128);
            $createBatch->setUserData64(0xDEADBEEF);
            $createBatch->setUserData32(0x12345678);
            $createBatch->setLedger(42);
            $createBatch->setCode(7);
            $client->createAccounts($createBatch);

            $ids = new IdBatch(1);
            $ids->add();
            $ids->setId($id);

            $accounts = $client->lookupAccounts($ids);

            $this->assertSame(1, $accounts->getLength());
            $accounts->rewind();
            $this->assertTrue($id->equals($accounts->getId()));
            $this->assertTrue($userData128->equals($accounts->getUserData128()));
            $this->assertSame(0xDEADBEEF, $accounts->getUserData64());
            $this->assertSame(0x12345678, $accounts->getUserData32());
            $this->assertSame(42, $accounts->getLedger());
            $this->assertSame(7, $accounts->getCode());
            $this->assertSame(0, $accounts->getFlags());
            $this->assertTrue($accounts->getDebitsPending()->equals(Uint128::zero()));
            $this->assertTrue($accounts->getDebitsPosted()->equals(Uint128::zero()));
            $this->assertTrue($accounts->getCreditsPending()->equals(Uint128::zero()));
            $this->assertTrue($accounts->getCreditsPosted()->equals(Uint128::zero()));
            $this->assertGreaterThan(0, $accounts->getTimestamp());
        } finally {
            $client->close();
        }
    }
}
