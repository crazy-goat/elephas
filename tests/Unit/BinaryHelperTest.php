<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;
use PHPUnit\Framework\TestCase;

class BinaryHelperTest extends TestCase
{
    /**
     * @param int<0, 255> $char
     */
    private function uint128(int $char): string
    {
        return str_repeat(\chr($char), 16);
    }

    public function testAccountSize(): void
    {
        $this->assertSame(128, BinaryHelper::ACCOUNT_SIZE);
    }

    public function testPackAccountReturns128Bytes(): void
    {
        $bytes = BinaryHelper::packAccount($this->sampleAccount());
        $this->assertSame(128, \strlen($bytes));
    }

    public function testPackAccountZeroFields(): void
    {
        $bytes = BinaryHelper::packAccount($this->zeroAccount());
        $this->assertSame(128, \strlen($bytes));
        $this->assertSame(\str_repeat("\0", 128), $bytes);
    }

    public function testPackUnpackAccountRoundtrip(): void
    {
        $fields = $this->sampleAccount();
        $bytes = BinaryHelper::packAccount($fields);
        $result = BinaryHelper::unpackAccount($bytes);
        $this->assertSame($fields, $result);
    }

    public function testUnpackAccountReturnsFields(): void
    {
        $result = BinaryHelper::unpackAccount(\str_repeat("\0", 128));
        $expected = $this->zeroAccount();
        $this->assertSame($expected, $result);
    }

    public function testPackAccountIdField(): void
    {
        $id = $this->uint128(0xAB);
        $bytes = BinaryHelper::packAccount(['id' => $id] + $this->zeroAccount());
        $this->assertSame(\substr($id, 0, 16), \substr($bytes, 0, 16));
    }

    public function testPackAccountFlagsField(): void
    {
        $bytes = BinaryHelper::packAccount(['flags' => 0xABCD] + $this->zeroAccount());
        $this->assertSame(\chr(0xCD), \substr($bytes, 118, 1));
        $this->assertSame(\chr(0xAB), \substr($bytes, 119, 1));
    }

    public function testPackAccountTimestampField(): void
    {
        $bytes = BinaryHelper::packAccount(['timestamp' => 0x0102030405060708] + $this->zeroAccount());
        $this->assertSame("\x08\x07\x06\x05\x04\x03\x02\x01", \substr($bytes, 120, 8));
    }

    public function testPackAccountCodeField(): void
    {
        $bytes = BinaryHelper::packAccount(['code' => 0x0102] + $this->zeroAccount());
        $this->assertSame("\x02\x01", \substr($bytes, 116, 2));
    }

    public function testPackAccountLedgerField(): void
    {
        $bytes = BinaryHelper::packAccount(['ledger' => 0x01020304] + $this->zeroAccount());
        $this->assertSame("\x04\x03\x02\x01", \substr($bytes, 112, 4));
    }

    public function testTransferSize(): void
    {
        $this->assertSame(128, BinaryHelper::TRANSFER_SIZE);
    }

    public function testPackTransferReturns128Bytes(): void
    {
        $bytes = BinaryHelper::packTransfer($this->sampleTransfer());
        $this->assertSame(128, \strlen($bytes));
    }

    public function testPackTransferZeroFields(): void
    {
        $bytes = BinaryHelper::packTransfer($this->zeroTransfer());
        $this->assertSame(128, \strlen($bytes));
        $this->assertSame(\str_repeat("\0", 128), $bytes);
    }

    public function testPackUnpackTransferRoundtrip(): void
    {
        $fields = $this->sampleTransfer();
        $bytes = BinaryHelper::packTransfer($fields);
        $result = BinaryHelper::unpackTransfer($bytes);
        $this->assertSame($fields, $result);
    }

    public function testUnpackTransferZeroInput(): void
    {
        $result = BinaryHelper::unpackTransfer(\str_repeat("\0", 128));
        $this->assertSame($this->zeroTransfer(), $result);
    }

    public function testPackTransferAmountField(): void
    {
        $bytes = BinaryHelper::packTransfer(['amount' => $this->uint128(0x42)] + $this->zeroTransfer());
        $this->assertSame(\str_repeat(\chr(0x42), 16), \substr($bytes, 48, 16));
    }

    public function testPackTransferPendingIdField(): void
    {
        $bytes = BinaryHelper::packTransfer(['pending_id' => $this->uint128(0x99)] + $this->zeroTransfer());
        $this->assertSame(\str_repeat(\chr(0x99), 16), \substr($bytes, 64, 16));
    }

    public function testAccountFilterSize(): void
    {
        $this->assertSame(128, BinaryHelper::ACCOUNT_FILTER_SIZE);
    }

    public function testPackAccountFilterReturns128Bytes(): void
    {
        $bytes = BinaryHelper::packAccountFilter($this->sampleAccountFilter());
        $this->assertSame(128, \strlen($bytes));
    }

    public function testPackUnpackAccountFilterRoundtrip(): void
    {
        $fields = $this->sampleAccountFilter();
        $bytes = BinaryHelper::packAccountFilter($fields);
        $result = BinaryHelper::unpackAccountFilter($bytes);
        $this->assertSame($fields, $result);
    }

    public function testAccountBalanceSize(): void
    {
        $this->assertSame(128, BinaryHelper::ACCOUNT_BALANCE_SIZE);
    }

    public function testPackAccountBalanceReturns128Bytes(): void
    {
        $bytes = BinaryHelper::packAccountBalance($this->sampleAccountBalance());
        $this->assertSame(128, \strlen($bytes));
    }

    public function testPackUnpackAccountBalanceRoundtrip(): void
    {
        $fields = $this->sampleAccountBalance();
        $bytes = BinaryHelper::packAccountBalance($fields);
        $result = BinaryHelper::unpackAccountBalance($bytes);
        $this->assertSame($fields, $result);
    }

    public function testQueryFilterSize(): void
    {
        $this->assertSame(64, BinaryHelper::QUERY_FILTER_SIZE);
    }

    public function testPackQueryFilterReturns64Bytes(): void
    {
        $bytes = BinaryHelper::packQueryFilter($this->sampleQueryFilter());
        $this->assertSame(64, \strlen($bytes));
    }

    public function testPackUnpackQueryFilterRoundtrip(): void
    {
        $fields = $this->sampleQueryFilter();
        $bytes = BinaryHelper::packQueryFilter($fields);
        $result = BinaryHelper::unpackQueryFilter($bytes);
        $this->assertSame($fields, $result);
    }

    public function testCreateAccountResultSize(): void
    {
        $this->assertSame(16, BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE);
    }

    public function testUnpackCreateAccountResult(): void
    {
        $bytes = \pack('PVV', 1234567890, 0xFFFFFFFF, 0);
        $result = BinaryHelper::unpackCreateAccountResult($bytes);

        $this->assertSame(1234567890, $result['timestamp']);
        $this->assertSame(0xFFFFFFFF, $result['status']);
        $this->assertSame(0, $result['reserved']);
    }

    public function testUnpackCreateAccountResultZero(): void
    {
        $result = BinaryHelper::unpackCreateAccountResult(\str_repeat("\0", 16));

        $this->assertSame(0, $result['timestamp']);
        $this->assertSame(0, $result['status']);
        $this->assertSame(0, $result['reserved']);
    }

    public function testCreateTransferResultSize(): void
    {
        $this->assertSame(16, BinaryHelper::CREATE_TRANSFER_RESULT_SIZE);
    }

    public function testUnpackCreateTransferResult(): void
    {
        $bytes = \pack('PVV', 987654321, 1, 0);
        $result = BinaryHelper::unpackCreateTransferResult($bytes);

        $this->assertSame(987654321, $result['timestamp']);
        $this->assertSame(1, $result['status']);
        $this->assertSame(0, $result['reserved']);
    }

    public function testUint128Size(): void
    {
        $this->assertSame(16, BinaryHelper::UINT128_SIZE);
    }

    public function testPackUint128(): void
    {
        $value = Uint128::fromParts(0x0102030405060708, 0x090A0B0C0D0E0F10);
        $bytes = BinaryHelper::packUint128($value);

        $this->assertSame(16, \strlen($bytes));
        $this->assertSame("\x08\x07\x06\x05\x04\x03\x02\x01\x10\x0F\x0E\x0D\x0C\x0B\x0A\x09", $bytes);
    }

    public function testUnpackUint128(): void
    {
        $bytes = "\x08\x07\x06\x05\x04\x03\x02\x01\x10\x0F\x0E\x0D\x0C\x0B\x0A\x09";
        $result = BinaryHelper::unpackUint128($bytes);

        $this->assertSame(0x0102030405060708, $result->toArray()['low']);
        $this->assertSame(0x090A0B0C0D0E0F10, $result->toArray()['high']);
    }

    public function testPackUnpackUint128Roundtrip(): void
    {
        $original = Uint128::fromString('123456789012345678901234567890123456');
        $bytes = BinaryHelper::packUint128($original);
        $result = BinaryHelper::unpackUint128($bytes);

        $this->assertTrue($original->equals($result));
    }

    public function testPackAccountWithUint128Objects(): void
    {
        $fields = $this->sampleAccount();
        $fields['id'] = Uint128::fromBytes($fields['id']);
        $fields['user_data_128'] = Uint128::fromBytes($fields['user_data_128']);

        $bytes = BinaryHelper::packAccount($fields);
        $result = BinaryHelper::unpackAccount($bytes);

        $this->assertSame($this->uint128(1), $result['id']);
        $this->assertSame($this->uint128(6), $result['user_data_128']);
    }

    // ──────────────────────────────────────────────
    //  Sample data providers
    // ──────────────────────────────────────────────

    /**
     * @return array{
     *   id: string, debits_pending: string, debits_posted: string,
     *   credits_pending: string, credits_posted: string, user_data_128: string,
     *   user_data_64: int, user_data_32: int, reserved: int,
     *   ledger: int, code: int, flags: int, timestamp: int,
     * }
     */
    private function zeroAccount(): array
    {
        return [
            'id' => \str_repeat("\0", 16),
            'debits_pending' => \str_repeat("\0", 16),
            'debits_posted' => \str_repeat("\0", 16),
            'credits_pending' => \str_repeat("\0", 16),
            'credits_posted' => \str_repeat("\0", 16),
            'user_data_128' => \str_repeat("\0", 16),
            'user_data_64' => 0,
            'user_data_32' => 0,
            'reserved' => 0,
            'ledger' => 0,
            'code' => 0,
            'flags' => 0,
            'timestamp' => 0,
        ];
    }

    /**
     * @return array{
     *   id: string, debits_pending: string, debits_posted: string,
     *   credits_pending: string, credits_posted: string, user_data_128: string,
     *   user_data_64: int, user_data_32: int, reserved: int,
     *   ledger: int, code: int, flags: int, timestamp: int,
     * }
     */
    private function sampleAccount(): array
    {
        return [
            'id' => $this->uint128(1),
            'debits_pending' => $this->uint128(2),
            'debits_posted' => $this->uint128(3),
            'credits_pending' => $this->uint128(4),
            'credits_posted' => $this->uint128(5),
            'user_data_128' => $this->uint128(6),
            'user_data_64' => 42,
            'user_data_32' => 100,
            'reserved' => 0,
            'ledger' => 1,
            'code' => 2,
            'flags' => 3,
            'timestamp' => 12345,
        ];
    }

    /**
     * @return array{
     *   id: string, debit_account_id: string, credit_account_id: string,
     *   amount: string, pending_id: string, user_data_128: string,
     *   user_data_64: int, user_data_32: int, timeout: int,
     *   ledger: int, code: int, flags: int, timestamp: int,
     * }
     */
    private function zeroTransfer(): array
    {
        return [
            'id' => \str_repeat("\0", 16),
            'debit_account_id' => \str_repeat("\0", 16),
            'credit_account_id' => \str_repeat("\0", 16),
            'amount' => \str_repeat("\0", 16),
            'pending_id' => \str_repeat("\0", 16),
            'user_data_128' => \str_repeat("\0", 16),
            'user_data_64' => 0,
            'user_data_32' => 0,
            'timeout' => 0,
            'ledger' => 0,
            'code' => 0,
            'flags' => 0,
            'timestamp' => 0,
        ];
    }

    /**
     * @return array{
     *   id: string, debit_account_id: string, credit_account_id: string,
     *   amount: string, pending_id: string, user_data_128: string,
     *   user_data_64: int, user_data_32: int, timeout: int,
     *   ledger: int, code: int, flags: int, timestamp: int,
     * }
     */
    private function sampleTransfer(): array
    {
        return [
            'id' => $this->uint128(1),
            'debit_account_id' => $this->uint128(2),
            'credit_account_id' => $this->uint128(3),
            'amount' => $this->uint128(4),
            'pending_id' => $this->uint128(5),
            'user_data_128' => $this->uint128(6),
            'user_data_64' => 42,
            'user_data_32' => 100,
            'timeout' => 0,
            'ledger' => 1,
            'code' => 2,
            'flags' => 3,
            'timestamp' => 12345,
        ];
    }

    /**
     * @return array{
     *   account_id: string, user_data_128: string,
     *   user_data_64: int, user_data_32: int, code: int,
     *   timestamp_min: int, timestamp_max: int, limit: int, flags: int,
     * }
     */
    private function sampleAccountFilter(): array
    {
        return [
            'account_id' => $this->uint128(1),
            'user_data_128' => $this->uint128(2),
            'user_data_64' => 42,
            'user_data_32' => 100,
            'code' => 255,
            'timestamp_min' => 1000,
            'timestamp_max' => 2000,
            'limit' => 10,
            'flags' => 0,
        ];
    }

    /**
     * @return array{
     *   debits_pending: string, debits_posted: string,
     *   credits_pending: string, credits_posted: string, timestamp: int,
     * }
     */
    private function sampleAccountBalance(): array
    {
        return [
            'debits_pending' => $this->uint128(1),
            'debits_posted' => $this->uint128(2),
            'credits_pending' => $this->uint128(3),
            'credits_posted' => $this->uint128(4),
            'timestamp' => 98765,
        ];
    }

    /**
     * @return array{
     *   user_data_128: string, user_data_64: int, user_data_32: int,
     *   ledger: int, code: int,
     *   timestamp_min: int, timestamp_max: int, limit: int, flags: int,
     * }
     */
    private function sampleQueryFilter(): array
    {
        return [
            'user_data_128' => $this->uint128(1),
            'user_data_64' => 42,
            'user_data_32' => 100,
            'ledger' => 1,
            'code' => 255,
            'timestamp_min' => 1000,
            'timestamp_max' => 2000,
            'limit' => 10,
            'flags' => 0,
        ];
    }
}
