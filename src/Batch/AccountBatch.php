<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Internal\BinaryRange;
use CrazyGoat\Elephas\Uint128\Uint128;

class AccountBatch extends AbstractBatch
{
    private const ID = 0;
    private const DEBITS_PENDING = 16;
    private const DEBITS_POSTED = 32;
    private const CREDITS_PENDING = 48;
    private const CREDITS_POSTED = 64;
    private const USER_DATA_128 = 80;
    private const USER_DATA_64 = 96;
    private const USER_DATA_32 = 104;
    private const LEDGER = 112;
    private const CODE = 116;
    private const FLAGS = 118;
    private const TIMESTAMP = 120;

    protected function getStructSize(): int
    {
        return BinaryHelper::ACCOUNT_SIZE;
    }

    public static function fromBuffer(string $buffer): self
    {
        return self::fromBufferInternal($buffer, BinaryHelper::ACCOUNT_SIZE);
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

    public function getDebitsPending(): Uint128
    {
        return $this->readUint128(self::DEBITS_PENDING);
    }

    public function setDebitsPending(Uint128 $value): void
    {
        $this->writeUint128(self::DEBITS_PENDING, $value);
    }

    public function getDebitsPosted(): Uint128
    {
        return $this->readUint128(self::DEBITS_POSTED);
    }

    public function setDebitsPosted(Uint128 $value): void
    {
        $this->writeUint128(self::DEBITS_POSTED, $value);
    }

    public function getCreditsPending(): Uint128
    {
        return $this->readUint128(self::CREDITS_PENDING);
    }

    public function setCreditsPending(Uint128 $value): void
    {
        $this->writeUint128(self::CREDITS_PENDING, $value);
    }

    public function getCreditsPosted(): Uint128
    {
        return $this->readUint128(self::CREDITS_POSTED);
    }

    public function setCreditsPosted(Uint128 $value): void
    {
        $this->writeUint128(self::CREDITS_POSTED, $value);
    }

    public function getUserData128(): Uint128
    {
        return $this->readUint128(self::USER_DATA_128);
    }

    public function setUserData128(Uint128 $value): void
    {
        $this->writeUint128(self::USER_DATA_128, $value);
    }

    public function getUserData64(): int
    {
        return $this->readUint64(self::USER_DATA_64);
    }

    public function setUserData64(int $value): void
    {
        BinaryRange::assertUint64($value, 'user_data_64');
        $this->writeUint64(self::USER_DATA_64, $value);
    }

    public function getUserData32(): int
    {
        return $this->readUint32(self::USER_DATA_32);
    }

    public function setUserData32(int $value): void
    {
        BinaryRange::assertUint32($value, 'user_data_32');
        $this->writeUint32(self::USER_DATA_32, $value);
    }

    public function getLedger(): int
    {
        return $this->readUint32(self::LEDGER);
    }

    public function setLedger(int $value): void
    {
        BinaryRange::assertUint32($value, 'ledger');
        $this->writeUint32(self::LEDGER, $value);
    }

    public function getCode(): int
    {
        return $this->readUint16(self::CODE);
    }

    public function setCode(int $value): void
    {
        BinaryRange::assertUint16($value, 'code');
        $this->writeUint16(self::CODE, $value);
    }

    public function getFlags(): int
    {
        return $this->readUint16(self::FLAGS);
    }

    public function setFlags(int $value): void
    {
        BinaryRange::assertUint16($value, 'flags');
        $this->writeUint16(self::FLAGS, $value);
    }

    public function getTimestamp(): int
    {
        return $this->readUint64(self::TIMESTAMP);
    }
}
