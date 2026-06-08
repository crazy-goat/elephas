<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateAccountResult;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Exception\UnknownStatusException;
use CrazyGoat\Elephas\Internal\BinaryHelper;

class CreateAccountResultBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE;
    }

    public function add(): void
    {
        throw new \RuntimeException('CreateAccountResultBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function fromBuffer(string $buffer): self
    {
        return self::fromBufferInternal($buffer, BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE);
    }

    public function getResult(): CreateAccountResult
    {
        $this->requireValidPosition('read field');
        $data = $this->getBufferAtPosition($this->currentPosition);
        $unpacked = BinaryHelper::unpackCreateAccountResult($data);

        try {
            return new CreateAccountResult($unpacked['timestamp'], CreateAccountStatus::from($unpacked['status']));
        } catch (\ValueError) {
            throw UnknownStatusException::forEnum(CreateAccountStatus::class, $unpacked['status']);
        }
    }
}
