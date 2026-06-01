<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateAccountResult;
use CrazyGoat\Elephas\CreateAccountStatus;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

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
        $count = (int) \ceil(\strlen($buffer) / BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE);
        $batch = new self($count);
        $batch->buffer = $buffer;
        $batch->length = $count;

        return $batch;
    }

    public function getResult(): CreateAccountResult
    {
        $offset = $this->currentPosition * $this->getStructSize();
        $data = \substr($this->buffer, $offset, $this->getStructSize());
        $unpacked = BinaryHelper::unpackCreateAccountResult($data);

        $id = Uint128::fromParts($unpacked['timestamp'], 0);

        return new CreateAccountResult($id, CreateAccountStatus::from($unpacked['status']));
    }
}
