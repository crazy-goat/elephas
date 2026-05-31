<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\AccountFilterFlags;
use CrazyGoat\Elephas\AccountFlags;
use CrazyGoat\Elephas\ClientStatus;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\InitStatus;
use CrazyGoat\Elephas\Operation;
use CrazyGoat\Elephas\PacketStatus;
use CrazyGoat\Elephas\QueryFilterFlags;
use CrazyGoat\Elephas\TransferFlags;
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

    // ──────────────────────────────────────────────
    //  Operation
    // ──────────────────────────────────────────────

    #[Test]
    public function operationCases(): void
    {
        $cases = Operation::cases();
        $this->assertCount(9, $cases);
        $expected = [
            'PULSE',
            'CREATE_ACCOUNTS',
            'CREATE_TRANSFERS',
            'LOOKUP_ACCOUNTS',
            'LOOKUP_TRANSFERS',
            'GET_ACCOUNT_TRANSFERS',
            'GET_ACCOUNT_BALANCES',
            'QUERY_ACCOUNTS',
            'QUERY_TRANSFERS',
        ];
        foreach ($expected as $name) {
            $this->assertTrue(\defined(Operation::class . "::{$name}"), "Operation case {$name} should exist");
        }
    }

    #[Test]
    public function operationValues(): void
    {
        $this->assertSame(128, Operation::PULSE->value);
        $this->assertSame(146, Operation::CREATE_ACCOUNTS->value);
        $this->assertSame(147, Operation::CREATE_TRANSFERS->value);
        $this->assertSame(148, Operation::LOOKUP_ACCOUNTS->value);
        $this->assertSame(149, Operation::LOOKUP_TRANSFERS->value);
        $this->assertSame(150, Operation::GET_ACCOUNT_TRANSFERS->value);
        $this->assertSame(151, Operation::GET_ACCOUNT_BALANCES->value);
        $this->assertSame(152, Operation::QUERY_ACCOUNTS->value);
        $this->assertSame(153, Operation::QUERY_TRANSFERS->value);
    }

    #[Test]
    public function operationFromBackedValue(): void
    {
        $this->assertSame(Operation::PULSE, Operation::from(128));
        $this->assertSame(Operation::CREATE_ACCOUNTS, Operation::from(146));
        $this->assertSame(Operation::QUERY_TRANSFERS, Operation::from(153));
    }

    #[Test]
    public function operationIsEnum(): void
    {
        $this->assertTrue((new \ReflectionClass(Operation::class))->isEnum());
    }

    // ──────────────────────────────────────────────
    //  AccountFlags
    // ──────────────────────────────────────────────

    #[Test]
    public function accountFlagsConstants(): void
    {
        $this->assertSame(0, AccountFlags::NONE);
        $this->assertSame(1, AccountFlags::LINKED);
        $this->assertSame(2, AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS);
        $this->assertSame(4, AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS);
        $this->assertSame(8, AccountFlags::HISTORY);
        $this->assertSame(16, AccountFlags::IMPORTED);
        $this->assertSame(32, AccountFlags::CLOSED);
        $this->assertSame(64, AccountFlags::ZERO_VALUE_TRANSFERS);
    }

    #[Test]
    public function accountFlagsLinked(): void
    {
        $this->assertSame(1, AccountFlags::LINKED);
    }

    #[Test]
    public function accountFlagsDebitsMustNotExceedCredits(): void
    {
        $this->assertSame(2, AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS);
    }

    #[Test]
    public function accountFlagsCreditsMustNotExceedDebits(): void
    {
        $this->assertSame(4, AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS);
    }

    #[Test]
    public function accountFlagsHistory(): void
    {
        $this->assertSame(8, AccountFlags::HISTORY);
    }

    #[Test]
    public function accountFlagsImported(): void
    {
        $this->assertSame(16, AccountFlags::IMPORTED);
    }

    #[Test]
    public function accountFlagsClosed(): void
    {
        $this->assertSame(32, AccountFlags::CLOSED);
    }

    #[Test]
    public function accountFlagsZeroValueTransfers(): void
    {
        $this->assertSame(64, AccountFlags::ZERO_VALUE_TRANSFERS);
    }

    #[Test]
    public function accountFlagsCombine(): void
    {
        $combined = AccountFlags::combine(AccountFlags::LINKED, AccountFlags::HISTORY, AccountFlags::CLOSED);
        $this->assertSame(41, $combined); // 1 | 8 | 32 = 41
    }

    #[Test]
    public function accountFlagsCombineNone(): void
    {
        $this->assertSame(0, AccountFlags::combine());
    }

    #[Test]
    public function accountFlagsNone(): void
    {
        $this->assertSame(0, AccountFlags::NONE);
    }

    // ──────────────────────────────────────────────
    //  TransferFlags
    // ──────────────────────────────────────────────

    #[Test]
    public function transferFlagsConstants(): void
    {
        $this->assertSame(0, TransferFlags::NONE);
        $this->assertSame(1, TransferFlags::LINKED);
        $this->assertSame(2, TransferFlags::PENDING);
        $this->assertSame(4, TransferFlags::POST_PENDING_TRANSFER);
        $this->assertSame(8, TransferFlags::VOID_PENDING_TRANSFER);
        $this->assertSame(16, TransferFlags::BALANCING_DEBIT);
        $this->assertSame(32, TransferFlags::BALANCING_CREDIT);
        $this->assertSame(64, TransferFlags::CLOSING_DEBIT);
        $this->assertSame(128, TransferFlags::CLOSING_CREDIT);
        $this->assertSame(256, TransferFlags::IMPORTED);
        $this->assertSame(512, TransferFlags::ZERO_VALUE_TRANSFERS);
    }

    #[Test]
    public function transferFlagsLinked(): void
    {
        $this->assertSame(1, TransferFlags::LINKED);
    }

    #[Test]
    public function transferFlagsPending(): void
    {
        $this->assertSame(2, TransferFlags::PENDING);
    }

    #[Test]
    public function transferFlagsPostPendingTransfer(): void
    {
        $this->assertSame(4, TransferFlags::POST_PENDING_TRANSFER);
    }

    #[Test]
    public function transferFlagsVoidPendingTransfer(): void
    {
        $this->assertSame(8, TransferFlags::VOID_PENDING_TRANSFER);
    }

    #[Test]
    public function transferFlagsBalancingDebit(): void
    {
        $this->assertSame(16, TransferFlags::BALANCING_DEBIT);
    }

    #[Test]
    public function transferFlagsBalancingCredit(): void
    {
        $this->assertSame(32, TransferFlags::BALANCING_CREDIT);
    }

    #[Test]
    public function transferFlagsClosingDebit(): void
    {
        $this->assertSame(64, TransferFlags::CLOSING_DEBIT);
    }

    #[Test]
    public function transferFlagsClosingCredit(): void
    {
        $this->assertSame(128, TransferFlags::CLOSING_CREDIT);
    }

    #[Test]
    public function transferFlagsImported(): void
    {
        $this->assertSame(256, TransferFlags::IMPORTED);
    }

    #[Test]
    public function transferFlagsZeroValueTransfers(): void
    {
        $this->assertSame(512, TransferFlags::ZERO_VALUE_TRANSFERS);
    }

    #[Test]
    public function transferFlagsCombine(): void
    {
        $combined = TransferFlags::combine(TransferFlags::LINKED, TransferFlags::PENDING, TransferFlags::IMPORTED);
        $this->assertSame(259, $combined); // 1 | 2 | 256 = 259
    }

    #[Test]
    public function transferFlagsCombineNone(): void
    {
        $this->assertSame(0, TransferFlags::combine());
    }

    #[Test]
    public function transferFlagsNone(): void
    {
        $this->assertSame(0, TransferFlags::NONE);
    }
}
