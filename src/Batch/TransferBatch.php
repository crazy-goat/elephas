<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Internal\BinaryRange;
use CrazyGoat\Elephas\Uint128\Uint128;

class TransferBatch extends AbstractBatch
{
    private const ID = 0;
    private const DEBIT_ACCOUNT_ID = 16;
    private const CREDIT_ACCOUNT_ID = 32;
    private const AMOUNT = 48;
    private const PENDING_ID = 64;
    private const USER_DATA_128 = 80;
    private const USER_DATA_64 = 96;
    private const USER_DATA_32 = 104;
    private const TIMEOUT = 108;
    private const LEDGER = 112;
    private const CODE = 116;
    private const FLAGS = 118;
    private const TIMESTAMP = 120;

    protected function getStructSize(): int
    {
        return BinaryHelper::TRANSFER_SIZE;
    }

    public static function fromBuffer(string $buffer): self
    {
        return self::fromBufferInternal($buffer, BinaryHelper::TRANSFER_SIZE);
    }

    public function setId(Uint128 $id): void
    {
        $this->writeUint128(self::ID, $id);
    }

    public function getId(): Uint128
    {
        return $this->readUint128(self::ID);
    }

    public function isFound(): bool
    {
        return $this->isValidPosition() && !$this->getId()->isZero();
    }

    public function setDebitAccountId(Uint128 $id): void
    {
        $this->writeUint128(self::DEBIT_ACCOUNT_ID, $id);
    }

    public function getDebitAccountId(): Uint128
    {
        return $this->readUint128(self::DEBIT_ACCOUNT_ID);
    }

    public function setCreditAccountId(Uint128 $id): void
    {
        $this->writeUint128(self::CREDIT_ACCOUNT_ID, $id);
    }

    public function getCreditAccountId(): Uint128
    {
        return $this->readUint128(self::CREDIT_ACCOUNT_ID);
    }

    public function setAmount(Uint128 $amount): void
    {
        $this->writeUint128(self::AMOUNT, $amount);
    }

    public function getAmount(): Uint128
    {
        return $this->readUint128(self::AMOUNT);
    }

    public function setPendingId(Uint128 $pendingId): void
    {
        $this->writeUint128(self::PENDING_ID, $pendingId);
    }

    public function getPendingId(): Uint128
    {
        return $this->readUint128(self::PENDING_ID);
    }

    public function setUserData128(Uint128 $value): void
    {
        $this->writeUint128(self::USER_DATA_128, $value);
    }

    public function getUserData128(): Uint128
    {
        return $this->readUint128(self::USER_DATA_128);
    }

    public function setUserData64(int $value): void
    {
        BinaryRange::assertUint64($value, 'user_data_64');
        $this->writeUint64(self::USER_DATA_64, $value);
    }

    public function getUserData64(): int
    {
        return $this->readUint64(self::USER_DATA_64);
    }

    public function setUserData32(int $value): void
    {
        BinaryRange::assertUint32($value, 'user_data_32');
        $this->writeUint32(self::USER_DATA_32, $value);
    }

    public function getUserData32(): int
    {
        return $this->readUint32(self::USER_DATA_32);
    }

    public function setTimeout(int $value): void
    {
        BinaryRange::assertUint32($value, 'timeout');
        $this->writeUint32(self::TIMEOUT, $value);
    }

    public function getTimeout(): int
    {
        return $this->readUint32(self::TIMEOUT);
    }

    public function setLedger(int $value): void
    {
        BinaryRange::assertUint32($value, 'ledger');
        $this->writeUint32(self::LEDGER, $value);
    }

    public function getLedger(): int
    {
        return $this->readUint32(self::LEDGER);
    }

    public function setCode(int $value): void
    {
        BinaryRange::assertUint16($value, 'code');
        $this->writeUint16(self::CODE, $value);
    }

    public function getCode(): int
    {
        return $this->readUint16(self::CODE);
    }

    public function setFlags(int $value): void
    {
        BinaryRange::assertUint16($value, 'flags');
        $this->writeUint16(self::FLAGS, $value);
    }

    public function getFlags(): int
    {
        return $this->readUint16(self::FLAGS);
    }

    public function getTimestamp(): int
    {
        return $this->readUint64(self::TIMESTAMP);
    }
}
