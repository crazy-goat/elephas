<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\AccountBalance;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

class AccountBalanceBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return BinaryHelper::ACCOUNT_BALANCE_SIZE;
    }

    public function add(): void
    {
        throw new \RuntimeException('AccountBalanceBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function fromBuffer(string $buffer): self
    {
        return self::fromBufferInternal($buffer, BinaryHelper::ACCOUNT_BALANCE_SIZE);
    }

    public function getBalance(): AccountBalance
    {
        $this->requireValidPosition('read field');
        $data = $this->getBufferAtPosition($this->currentPosition);
        $unpacked = BinaryHelper::unpackAccountBalance($data);

        return new AccountBalance(
            Uint128::fromBytes($unpacked['debits_pending']),
            Uint128::fromBytes($unpacked['debits_posted']),
            Uint128::fromBytes($unpacked['credits_pending']),
            Uint128::fromBytes($unpacked['credits_posted']),
            $unpacked['timestamp'],
        );
    }
}
