<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

class AccountFilterBatch extends AbstractBatch
{
    private const ACCOUNT_ID = 0;
    private const USER_DATA_128 = 16;
    private const USER_DATA_64 = 32;
    private const USER_DATA_32 = 40;
    private const CODE = 44;
    private const TIMESTAMP_MIN = 104;
    private const TIMESTAMP_MAX = 112;
    private const LIMIT = 120;
    private const FLAGS = 124;

    protected function getStructSize(): int
    {
        return BinaryHelper::ACCOUNT_FILTER_SIZE;
    }

    public static function fromBuffer(string $buffer): self
    {
        $count = (int) \ceil(\strlen($buffer) / BinaryHelper::ACCOUNT_FILTER_SIZE);
        $batch = new self($count);
        $batch->buffer = $buffer;
        $batch->length = $count;

        return $batch;
    }

    public function setAccountId(Uint128 $id): void
    {
        $this->writeUint128(self::ACCOUNT_ID, $id);
    }

    public function getAccountId(): Uint128
    {
        return $this->readUint128(self::ACCOUNT_ID);
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

    public function setCode(int $value): void
    {
        $this->writeUint16(self::CODE, $value);
    }

    public function getCode(): int
    {
        return $this->readUint16(self::CODE);
    }

    public function setTimestampMin(int $value): void
    {
        $this->writeUint64(self::TIMESTAMP_MIN, $value);
    }

    public function getTimestampMin(): int
    {
        return $this->readUint64(self::TIMESTAMP_MIN);
    }

    public function setTimestampMax(int $value): void
    {
        $this->writeUint64(self::TIMESTAMP_MAX, $value);
    }

    public function getTimestampMax(): int
    {
        return $this->readUint64(self::TIMESTAMP_MAX);
    }

    public function setLimit(int $value): void
    {
        $this->writeUint32(self::LIMIT, $value);
    }

    public function getLimit(): int
    {
        return $this->readUint32(self::LIMIT);
    }

    public function setFlags(int $value): void
    {
        $this->writeUint32(self::FLAGS, $value);
    }

    public function getFlags(): int
    {
        return $this->readUint32(self::FLAGS);
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
