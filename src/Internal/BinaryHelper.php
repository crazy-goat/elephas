<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Internal;

use CrazyGoat\Elephas\Uint128\Uint128;

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
     * @param array{
     *   id: Uint128|string,
     *   debits_pending: Uint128|string,
     *   debits_posted: Uint128|string,
     *   credits_pending: Uint128|string,
     *   credits_posted: Uint128|string,
     *   user_data_128: Uint128|string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   reserved: int,
     *   ledger: int,
     *   code: int,
     *   flags: int,
     *   timestamp: int,
     * } $fields
     */
    public static function packAccount(array $fields): string
    {
        return pack('P12PV3v2P', ...[
            ...self::uint128Parts($fields['id']),
            ...self::uint128Parts($fields['debits_pending']),
            ...self::uint128Parts($fields['debits_posted']),
            ...self::uint128Parts($fields['credits_pending']),
            ...self::uint128Parts($fields['credits_posted']),
            ...self::uint128Parts($fields['user_data_128']),
            $fields['user_data_64'],
            $fields['user_data_32'],
            $fields['reserved'],
            $fields['ledger'],
            $fields['code'],
            $fields['flags'],
            $fields['timestamp'],
        ]);
    }

    /**
     * @return array{
     *   id: string,
     *   debits_pending: string,
     *   debits_posted: string,
     *   credits_pending: string,
     *   credits_posted: string,
     *   user_data_128: string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   reserved: int,
     *   ledger: int,
     *   code: int,
     *   flags: int,
     *   timestamp: int,
     * }
     */
    public static function unpackAccount(string $bytes): array
    {
        $raw = unpack('Pid_low/Pid_high/Pdp_low/Pdp_high/Pdo_low/Pdo_high/Pcp_low/Pcp_high/Pco_low/Pco_high/Pud128_low/Pud128_high/Pud64/Vud32/Vreserved/Vledger/vcode/vflags/Ptimestamp', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack account data');
        }

        return [
            'id' => self::fromRawParts($raw['id_low'], $raw['id_high']),
            'debits_pending' => self::fromRawParts($raw['dp_low'], $raw['dp_high']),
            'debits_posted' => self::fromRawParts($raw['do_low'], $raw['do_high']),
            'credits_pending' => self::fromRawParts($raw['cp_low'], $raw['cp_high']),
            'credits_posted' => self::fromRawParts($raw['co_low'], $raw['co_high']),
            'user_data_128' => self::fromRawParts($raw['ud128_low'], $raw['ud128_high']),
            'user_data_64' => $raw['ud64'],
            'user_data_32' => $raw['ud32'],
            'reserved' => $raw['reserved'],
            'ledger' => $raw['ledger'],
            'code' => $raw['code'],
            'flags' => $raw['flags'],
            'timestamp' => $raw['timestamp'],
        ];
    }

    /**
     * @param array{
     *   id: Uint128|string,
     *   debit_account_id: Uint128|string,
     *   credit_account_id: Uint128|string,
     *   amount: Uint128|string,
     *   pending_id: Uint128|string,
     *   user_data_128: Uint128|string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   timeout: int,
     *   ledger: int,
     *   code: int,
     *   flags: int,
     *   timestamp: int,
     * } $fields
     */
    public static function packTransfer(array $fields): string
    {
        return pack('P12PV3v2P', ...[
            ...self::uint128Parts($fields['id']),
            ...self::uint128Parts($fields['debit_account_id']),
            ...self::uint128Parts($fields['credit_account_id']),
            ...self::uint128Parts($fields['amount']),
            ...self::uint128Parts($fields['pending_id']),
            ...self::uint128Parts($fields['user_data_128']),
            $fields['user_data_64'],
            $fields['user_data_32'],
            $fields['timeout'],
            $fields['ledger'],
            $fields['code'],
            $fields['flags'],
            $fields['timestamp'],
        ]);
    }

    /**
     * @return array{
     *   id: string,
     *   debit_account_id: string,
     *   credit_account_id: string,
     *   amount: string,
     *   pending_id: string,
     *   user_data_128: string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   timeout: int,
     *   ledger: int,
     *   code: int,
     *   flags: int,
     *   timestamp: int,
     * }
     */
    public static function unpackTransfer(string $bytes): array
    {
        $raw = unpack('Pid_low/Pid_high/Pda_low/Pda_high/Pca_low/Pca_high/Pamt_low/Pamt_high/Ppid_low/Ppid_high/Pud128_low/Pud128_high/Pud64/Vud32/Vtimeout/Vledger/vcode/vflags/Ptimestamp', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack transfer data');
        }

        return [
            'id' => self::fromRawParts($raw['id_low'], $raw['id_high']),
            'debit_account_id' => self::fromRawParts($raw['da_low'], $raw['da_high']),
            'credit_account_id' => self::fromRawParts($raw['ca_low'], $raw['ca_high']),
            'amount' => self::fromRawParts($raw['amt_low'], $raw['amt_high']),
            'pending_id' => self::fromRawParts($raw['pid_low'], $raw['pid_high']),
            'user_data_128' => self::fromRawParts($raw['ud128_low'], $raw['ud128_high']),
            'user_data_64' => $raw['ud64'],
            'user_data_32' => $raw['ud32'],
            'timeout' => $raw['timeout'],
            'ledger' => $raw['ledger'],
            'code' => $raw['code'],
            'flags' => $raw['flags'],
            'timestamp' => $raw['timestamp'],
        ];
    }

    /**
     * @param array{
     *   debits_pending: Uint128|string,
     *   debits_posted: Uint128|string,
     *   credits_pending: Uint128|string,
     *   credits_posted: Uint128|string,
     *   timestamp: int,
     * } $fields
     */
    public static function packAccountBalance(array $fields): string
    {
        return pack('P8Pa56', ...[
            ...self::uint128Parts($fields['debits_pending']),
            ...self::uint128Parts($fields['debits_posted']),
            ...self::uint128Parts($fields['credits_pending']),
            ...self::uint128Parts($fields['credits_posted']),
            $fields['timestamp'],
            str_repeat("\0", 56),
        ]);
    }

    /**
     * @return array{
     *   debits_pending: string,
     *   debits_posted: string,
     *   credits_pending: string,
     *   credits_posted: string,
     *   timestamp: int,
     * }
     */
    public static function unpackAccountBalance(string $bytes): array
    {
        $raw = unpack('Pdp_low/Pdp_high/Pdo_low/Pdo_high/Pcp_low/Pcp_high/Pco_low/Pco_high/Ptimestamp/a56reserved', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack account balance data');
        }

        return [
            'debits_pending' => self::fromRawParts($raw['dp_low'], $raw['dp_high']),
            'debits_posted' => self::fromRawParts($raw['do_low'], $raw['do_high']),
            'credits_pending' => self::fromRawParts($raw['cp_low'], $raw['cp_high']),
            'credits_posted' => self::fromRawParts($raw['co_low'], $raw['co_high']),
            'timestamp' => $raw['timestamp'],
        ];
    }

    /**
     * @param array{
     *   user_data_128: Uint128|string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   ledger: int,
     *   code: int,
     *   timestamp_min: int,
     *   timestamp_max: int,
     *   limit: int,
     *   flags: int,
     * } $fields
     */
    public static function packQueryFilter(array $fields): string
    {
        return pack('P3VVva6PPVV', ...[
            ...self::uint128Parts($fields['user_data_128']),
            $fields['user_data_64'],
            $fields['user_data_32'],
            $fields['ledger'],
            $fields['code'],
            str_repeat("\0", 6),
            $fields['timestamp_min'],
            $fields['timestamp_max'],
            $fields['limit'],
            $fields['flags'],
        ]);
    }

    /**
     * @return array{
     *   user_data_128: string,
     *   user_data_64: int,
     *   user_data_32: int,
     *   ledger: int,
     *   code: int,
     *   timestamp_min: int,
     *   timestamp_max: int,
     *   limit: int,
     *   flags: int,
     * }
     */
    public static function unpackQueryFilter(string $bytes): array
    {
        $raw = unpack('Pud128_low/Pud128_high/Pud64/Vud32/Vledger/vcode/a6reserved/Ptimestamp_min/Ptimestamp_max/Vlimit/Vflags', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack query filter data');
        }

        return [
            'user_data_128' => self::fromRawParts($raw['ud128_low'], $raw['ud128_high']),
            'user_data_64' => $raw['ud64'],
            'user_data_32' => $raw['ud32'],
            'ledger' => $raw['ledger'],
            'code' => $raw['code'],
            'timestamp_min' => $raw['timestamp_min'],
            'timestamp_max' => $raw['timestamp_max'],
            'limit' => $raw['limit'],
            'flags' => $raw['flags'],
        ];
    }

    /**
     * @return array{timestamp: int, status: int, reserved: int}
     */
    public static function unpackCreateAccountResult(string $bytes): array
    {
        $raw = unpack('Ptimestamp/Vstatus/Vreserved', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack create account result data');
        }

        return [
            'timestamp' => $raw['timestamp'],
            'status' => $raw['status'],
            'reserved' => $raw['reserved'],
        ];
    }

    /**
     * @return array{timestamp: int, status: int, reserved: int}
     */
    public static function unpackCreateTransferResult(string $bytes): array
    {
        $raw = unpack('Ptimestamp/Vstatus/Vreserved', $bytes);

        if ($raw === false) {
            throw new \RuntimeException('Failed to unpack create transfer result data');
        }

        return [
            'timestamp' => $raw['timestamp'],
            'status' => $raw['status'],
            'reserved' => $raw['reserved'],
        ];
    }

    public static function packUint128(Uint128 $value): string
    {
        return $value->toBytes();
    }

    public static function unpackUint128(string $bytes): Uint128
    {
        return Uint128::fromBytes($bytes);
    }

    /**
     * @return array{int, int}
     */
    private static function uint128Parts(Uint128|string $value): array
    {
        if ($value instanceof Uint128) {
            $value = $value->toBytes();
        }

        /** @var array{1: int, 2: int} $parts */
        $parts = unpack('P2', $value);

        return [$parts[1], $parts[2]];
    }

    private static function fromRawParts(int $low, int $high): string
    {
        return pack('P', $low) . pack('P', $high);
    }
}
