<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\AccountFilterFlags;
use CrazyGoat\Elephas\ClientStatus;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\InitStatus;
use CrazyGoat\Elephas\PacketStatus;
use CrazyGoat\Elephas\QueryFilterFlags;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all status enums.
 */
#[CoversNothing]
final class EnumTest extends TestCase
{
    // ──────────────────────────────────────────────
    //  CreateAccountStatus
    // ──────────────────────────────────────────────

    #[Test]
    public function createAccountStatusCreated(): void
    {
        $this->assertSame(0xFFFFFFFF, CreateAccountStatus::CREATED->value);
    }

    #[Test]
    public function createAccountStatusCases(): void
    {
        $cases = CreateAccountStatus::cases();
        $expected = 28; // CREATED (0xFFFFFFFF) + 27 error codes (1-27)
        $this->assertCount($expected, $cases);
    }

    #[Test]
    public function createAccountStatusFromBackedValue(): void
    {
        $this->assertSame(CreateAccountStatus::CREATED, CreateAccountStatus::from(0xFFFFFFFF));
        $this->assertSame(CreateAccountStatus::LINKED_EVENT_FAILED, CreateAccountStatus::from(1));
        $this->assertSame(CreateAccountStatus::EXISTS_WITH_DEBITS, CreateAccountStatus::from(11));
        $this->assertSame(CreateAccountStatus::IMPORTED_EVENT_TIMESTAMP_MUST_BE_IN_THE_FUTURE, CreateAccountStatus::from(27));
    }

    #[Test]
    public function createAccountStatusIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(CreateAccountStatus::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  CreateTransferStatus
    // ──────────────────────────────────────────────

    #[Test]
    public function createTransferStatusCreated(): void
    {
        $this->assertSame(0xFFFFFFFF, CreateTransferStatus::CREATED->value);
    }

    #[Test]
    public function createTransferStatusCases(): void
    {
        $cases = CreateTransferStatus::cases();
        $expected = 37; // CREATED (0xFFFFFFFF) + 36 error codes (1-36)
        $this->assertCount($expected, $cases);
    }

    #[Test]
    public function createTransferStatusFromBackedValue(): void
    {
        $this->assertSame(CreateTransferStatus::CREATED, CreateTransferStatus::from(0xFFFFFFFF));
        $this->assertSame(CreateTransferStatus::LINKED_EVENT_FAILED, CreateTransferStatus::from(1));
        $this->assertSame(CreateTransferStatus::DEBITS_ACCOUNTS_MUST_DIFFER, CreateTransferStatus::from(10));
        $this->assertSame(CreateTransferStatus::IMPORTED_EVENT_TIMESTAMP_MUST_BE_IN_THE_FUTURE, CreateTransferStatus::from(36));
    }

    #[Test]
    public function createTransferStatusIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(CreateTransferStatus::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  PacketStatus
    // ──────────────────────────────────────────────

    #[Test]
    public function packetStatusOk(): void
    {
        $this->assertSame(0, PacketStatus::OK->value);
    }

    #[Test]
    public function packetStatusCases(): void
    {
        $cases = PacketStatus::cases();
        $this->assertCount(7, $cases);
    }

    #[Test]
    public function packetStatusFromBackedValue(): void
    {
        $this->assertSame(PacketStatus::OK, PacketStatus::from(0));
        $this->assertSame(PacketStatus::TOO_MUCH_DATA, PacketStatus::from(1));
        $this->assertSame(PacketStatus::INVALID_OPERATION, PacketStatus::from(2));
        $this->assertSame(PacketStatus::INVALID_DATA_SIZE, PacketStatus::from(3));
        $this->assertSame(PacketStatus::ZERO_ADDRESS, PacketStatus::from(4));
        $this->assertSame(PacketStatus::ZERO_CLUSTER_ID, PacketStatus::from(5));
        $this->assertSame(PacketStatus::CONCURRENCY_MAX_EXCEEDED, PacketStatus::from(6));
    }

    #[Test]
    public function packetStatusIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(PacketStatus::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  InitStatus
    // ──────────────────────────────────────────────

    #[Test]
    public function initStatusSuccess(): void
    {
        $this->assertSame(0, InitStatus::SUCCESS->value);
    }

    #[Test]
    public function initStatusCases(): void
    {
        $cases = InitStatus::cases();
        $this->assertCount(6, $cases);
    }

    #[Test]
    public function initStatusFromBackedValue(): void
    {
        $this->assertSame(InitStatus::SUCCESS, InitStatus::from(0));
        $this->assertSame(InitStatus::UNEXPECTED, InitStatus::from(1));
        $this->assertSame(InitStatus::OUT_OF_MEMORY, InitStatus::from(2));
        $this->assertSame(InitStatus::INVALID_ADDRESS, InitStatus::from(3));
        $this->assertSame(InitStatus::SYSTEM_RESOURCES, InitStatus::from(4));
        $this->assertSame(InitStatus::NETWORK_SUBSYSTEM, InitStatus::from(5));
    }

    #[Test]
    public function initStatusIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(InitStatus::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  ClientStatus
    // ──────────────────────────────────────────────

    #[Test]
    public function clientStatusOk(): void
    {
        $this->assertSame(0, ClientStatus::OK->value);
    }

    #[Test]
    public function clientStatusCases(): void
    {
        $cases = ClientStatus::cases();
        $this->assertCount(4, $cases);
    }

    #[Test]
    public function clientStatusFromBackedValue(): void
    {
        $this->assertSame(ClientStatus::OK, ClientStatus::from(0));
        $this->assertSame(ClientStatus::INVALID, ClientStatus::from(1));
        $this->assertSame(ClientStatus::TOO_MUCH_DATA, ClientStatus::from(2));
        $this->assertSame(ClientStatus::CONCURRENCY_MAX_EXCEEDED, ClientStatus::from(3));
    }

    #[Test]
    public function clientStatusIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(ClientStatus::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  AccountFilterFlags
    // ──────────────────────────────────────────────

    #[Test]
    public function accountFilterFlagsConstants(): void
    {
        $this->assertSame(0, AccountFilterFlags::NONE);
        $this->assertSame(1, AccountFilterFlags::DEBITS);
        $this->assertSame(2, AccountFilterFlags::CREDITS);
        $this->assertSame(4, AccountFilterFlags::REVERSED);
    }

    #[Test]
    public function accountFilterFlagsDebits(): void
    {
        $this->assertSame(1, AccountFilterFlags::DEBITS);
    }

    #[Test]
    public function accountFilterFlagsCredits(): void
    {
        $this->assertSame(2, AccountFilterFlags::CREDITS);
    }

    #[Test]
    public function accountFilterFlagsReversed(): void
    {
        $this->assertSame(4, AccountFilterFlags::REVERSED);
    }

    #[Test]
    public function accountFilterFlagsCombine(): void
    {
        $combined = AccountFilterFlags::combine(AccountFilterFlags::DEBITS, AccountFilterFlags::CREDITS);
        $this->assertSame(3, $combined);
    }

    #[Test]
    public function accountFilterFlagsNone(): void
    {
        $this->assertSame(0, AccountFilterFlags::NONE);
    }

    // ──────────────────────────────────────────────
    //  QueryFilterFlags
    // ──────────────────────────────────────────────

    #[Test]
    public function queryFilterFlagsConstants(): void
    {
        $this->assertSame(0, QueryFilterFlags::NONE);
        $this->assertSame(1, QueryFilterFlags::REVERSED);
    }

    #[Test]
    public function queryFilterFlagsReversed(): void
    {
        $this->assertSame(1, QueryFilterFlags::REVERSED);
    }

    #[Test]
    public function queryFilterFlagsNone(): void
    {
        $this->assertSame(0, QueryFilterFlags::NONE);
    }
}
