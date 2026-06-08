<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Internal\BinaryRange;
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

    public function setCode(int $value): void
    {
        BinaryRange::assertUint16($value, 'code');
        $this->writeUint16(self::CODE, $value);
    }

    public function getCode(): int
    {
        return $this->readUint16(self::CODE);
    }

    public function setTimestampMin(int $value): void
    {
        BinaryRange::assertUint64($value, 'timestamp_min');
        $this->writeUint64(self::TIMESTAMP_MIN, $value);
    }

    public function getTimestampMin(): int
    {
        return $this->readUint64(self::TIMESTAMP_MIN);
    }

    public function setTimestampMax(int $value): void
    {
        BinaryRange::assertUint64($value, 'timestamp_max');
        $this->writeUint64(self::TIMESTAMP_MAX, $value);
    }

    public function getTimestampMax(): int
    {
        return $this->readUint64(self::TIMESTAMP_MAX);
    }

    public function setLimit(int $value): void
    {
        BinaryRange::assertUint32($value, 'limit');
        $this->writeUint32(self::LIMIT, $value);
    }

    public function getLimit(): int
    {
        return $this->readUint32(self::LIMIT);
    }

    public function setFlags(int $value): void
    {
        BinaryRange::assertUint32($value, 'flags');
        $this->writeUint32(self::FLAGS, $value);
    }

    public function getFlags(): int
    {
        return $this->readUint32(self::FLAGS);
    }
}
