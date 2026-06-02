<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
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
        $length = \strlen($buffer);
        $structSize = BinaryHelper::TRANSFER_SIZE;
        if ($length % $structSize !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'TransferBatch buffer size must be a multiple of %d bytes, got %d bytes',
                $structSize,
                $length,
            ));
        }
        $count = $length / $structSize;
        $batch = new self($count);
        $batch->buffer = $buffer;
        $batch->length = $count;

        return $batch;
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
        $this->writeUint64(self::USER_DATA_64, $value);
    }

    public function getUserData64(): int
    {
        return $this->readUint64(self::USER_DATA_64);
    }

    public function setUserData32(int $value): void
    {
        $this->writeUint32(self::USER_DATA_32, $value);
    }

    public function getUserData32(): int
    {
        return $this->readUint32(self::USER_DATA_32);
    }

    public function setTimeout(int $value): void
    {
        $this->writeUint32(self::TIMEOUT, $value);
    }

    public function getTimeout(): int
    {
        return $this->readUint32(self::TIMEOUT);
    }

    public function setLedger(int $value): void
    {
        $this->writeUint32(self::LEDGER, $value);
    }

    public function getLedger(): int
    {
        return $this->readUint32(self::LEDGER);
    }

    public function setCode(int $value): void
    {
        $this->writeUint16(self::CODE, $value);
    }

    public function getCode(): int
    {
        return $this->readUint16(self::CODE);
    }

    public function setFlags(int $value): void
    {
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

    private function readUint128(int $fieldOffset): Uint128
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;

        return Uint128::fromBytes(\substr($this->buffer, $offset, 16));
    }

    private function writeUint128(int $fieldOffset, Uint128 $value): void
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        $this->buffer = \substr_replace($this->buffer, $value->toBytes(), $offset, 16);
    }

    private function readUint64(int $fieldOffset): int
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('P', \substr($this->buffer, $offset, 8));

        return $unpacked[1];
    }

    private function writeUint64(int $fieldOffset, int $value): void
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        $this->buffer = \substr_replace($this->buffer, \pack('P', $value), $offset, 8);
    }

    private function readUint32(int $fieldOffset): int
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('V', \substr($this->buffer, $offset, 4));

        return $unpacked[1];
    }

    private function writeUint32(int $fieldOffset, int $value): void
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        $this->buffer = \substr_replace($this->buffer, \pack('V', $value), $offset, 4);
    }

    private function readUint16(int $fieldOffset): int
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('v', \substr($this->buffer, $offset, 2));

        return $unpacked[1];
    }

    private function writeUint16(int $fieldOffset, int $value): void
    {
        $offset = $this->currentPosition * $this->getStructSize() + $fieldOffset;
        $this->buffer = \substr_replace($this->buffer, \pack('v', $value), $offset, 2);
    }
}
