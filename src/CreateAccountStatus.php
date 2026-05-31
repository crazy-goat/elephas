<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas;

/**
 * TigerBeetle create account status codes.
 *
 * Maps to TB_CREATE_ACCOUNT_STATUS in tb_client.h.
 */
final class CreateAccountStatus
{
    public const OK = 0;

    public const LINKED_EVENT_FAILED = 1;

    public const LINKED_EVENT_CHAIN_BROKEN = 2;

    public const DEBIT_AMOUNT_MUST_NOT_EXCEED_CREDIT_LIMIT = 3;

    public const CREDIT_AMOUNT_MUST_NOT_EXCEED_DEBIT_LIMIT = 4;

    public const DEBIT_AMOUNT_MUST_BE_ZERO = 5;

    public const CREDIT_AMOUNT_MUST_BE_ZERO = 6;

    public const DEBIT_NOT_PENDING_TRANSFER = 7;

    public const CREDIT_NOT_PENDING_TRANSFER = 8;

    public const PENDING_TRANSFER_DEBIT_AMOUNT_MUST_NOT_EXCEED_CREDIT_LIMIT = 9;

    public const PENDING_TRANSFER_CREDIT_AMOUNT_MUST_NOT_EXCEED_DEBIT_LIMIT = 10;

    public const OVERFLOWS_DEBITS_PENDING = 11;

    public const OVERFLOWS_CREDITS_PENDING = 12;

    public const OVERFLOWS_DEBITS_POSTED = 13;

    public const OVERFLOWS_CREDITS_POSTED = 14;

    public const OVERFLOWS_DEBITS = 15;

    public const OVERFLOWS_CREDITS = 16;

    public const OVERFLOWS_TIMESTAMP = 17;

    public const RESERVED_FIELD = 18;

    public const RESERVED_FLAG = 19;

    public const IMPORTED_FIELD_EXPECTS_DEBIT = 20;

    public const IMPORTED_FIELD_EXPECTS_CREDIT = 21;

    public const IMPORTED_EVENT_FAILED = 22;

    public const IMPORTED_EVENT_CHAIN_BROKEN = 23;

    public const EXISTING = 24;

    public const ID_RESERVED_AS_INSERTED = 25;

    public const ID_ALREADY_FAILED_VALIDATION = 26;
}
