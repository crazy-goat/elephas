<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Internal;

use CrazyGoat\Elephas\Account;
use CrazyGoat\Elephas\AccountBalance;
use CrazyGoat\Elephas\AccountFilter;
use CrazyGoat\Elephas\CreateAccountResult;
use CrazyGoat\Elephas\CreateTransferResult;
use CrazyGoat\Elephas\QueryFilter;
use CrazyGoat\Elephas\Transfer;
use CrazyGoat\Elephas\Uint128\Uint128;

/**
 * Binary pack/unpack helper for TigerBeetle data structures.
 *
 * Provides static methods for converting between PHP objects
 * and binary representations compatible with tb_client.h.
 */
final class BinaryHelper
{
    public const ACCOUNT_SIZE = 128;

    public const TRANSFER_SIZE = 128;

    public const ACCOUNT_FILTER_SIZE = 128;

    public const ACCOUNT_BALANCE_SIZE = 128;

    public const QUERY_FILTER_SIZE = 64;

    public const CREATE_ACCOUNT_RESULT_SIZE = 16;

    public const CREATE_TRANSFER_RESULT_SIZE = 16;

    public const UINT128_SIZE = 16;

    /**
     * Pack an Account to binary format.
     *
     * TODO: implement
     */
    public static function packAccount(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to Account.
     *
     * TODO: implement
     */
    public static function unpackAccount(): Account
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack a Transfer to binary format.
     *
     * TODO: implement
     */
    public static function packTransfer(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to Transfer.
     *
     * TODO: implement
     */
    public static function unpackTransfer(): Transfer
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack an AccountFilter to binary format.
     *
     * TODO: implement
     */
    public static function packAccountFilter(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to AccountFilter.
     *
     * TODO: implement
     */
    public static function unpackAccountFilter(): AccountFilter
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack an AccountBalance to binary format.
     *
     * TODO: implement
     */
    public static function packAccountBalance(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to AccountBalance.
     *
     * TODO: implement
     */
    public static function unpackAccountBalance(): AccountBalance
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack a QueryFilter to binary format.
     *
     * TODO: implement
     */
    public static function packQueryFilter(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to QueryFilter.
     *
     * TODO: implement
     */
    public static function unpackQueryFilter(): QueryFilter
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack a CreateAccountResult to binary format.
     *
     * TODO: implement
     */
    public static function packCreateAccountResult(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to CreateAccountResult.
     *
     * TODO: implement
     */
    public static function unpackCreateAccountResult(): CreateAccountResult
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack a CreateTransferResult to binary format.
     *
     * TODO: implement
     */
    public static function packCreateTransferResult(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack binary data to CreateTransferResult.
     *
     * TODO: implement
     */
    public static function unpackCreateTransferResult(): CreateTransferResult
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Pack a Uint128 to 16 bytes little-endian.
     *
     * TODO: implement
     */
    public static function packUint128(): string
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Unpack 16 bytes little-endian to Uint128.
     *
     * TODO: implement
     */
    public static function unpackUint128(): Uint128
    {
        // TODO: implement
        throw new \RuntimeException('Not implemented');
    }
}
