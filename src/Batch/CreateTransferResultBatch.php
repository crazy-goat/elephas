<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateTransferResult;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Exception\UnknownStatusException;
use CrazyGoat\Elephas\Internal\BinaryHelper;

class CreateTransferResultBatch extends AbstractBatch
{
    protected function getStructSize(): int
    {
        return BinaryHelper::CREATE_TRANSFER_RESULT_SIZE;
    }

    public function add(): void
    {
        throw new \RuntimeException('CreateTransferResultBatch is read-only');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function fromBuffer(string $buffer): self
    {
        return self::fromBufferInternal($buffer, BinaryHelper::CREATE_TRANSFER_RESULT_SIZE);
    }

    public function getResult(): CreateTransferResult
    {
        $this->requireValidPosition('read field');
        $data = $this->getBufferAtPosition($this->currentPosition);
        $unpacked = BinaryHelper::unpackCreateTransferResult($data);

        try {
            return new CreateTransferResult($unpacked['timestamp'], CreateTransferStatus::from($unpacked['status']));
        } catch (\ValueError) {
            throw UnknownStatusException::forEnum(CreateTransferStatus::class, $unpacked['status']);
        }
    }
}
