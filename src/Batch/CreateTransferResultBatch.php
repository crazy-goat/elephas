<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\CreateTransferResult;
use CrazyGoat\Elephas\CreateTransferStatus;
use CrazyGoat\Elephas\Internal\BinaryHelper;
use CrazyGoat\Elephas\Uint128\Uint128;

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
        $length = \strlen($buffer);
        $structSize = BinaryHelper::CREATE_TRANSFER_RESULT_SIZE;
        if ($length % $structSize !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'CreateTransferResultBatch buffer size must be a multiple of %d bytes, got %d bytes',
                $structSize,
                $length,
            ));
        }
        $count = (int) ($length / $structSize);
        $batch = new self($count);
        $batch->buffer = $buffer;
        $batch->length = $count;

        return $batch;
    }

    public function getResult(): CreateTransferResult
    {
        $offset = $this->currentPosition * $this->getStructSize();
        $data = \substr($this->buffer, $offset, $this->getStructSize());
        $unpacked = BinaryHelper::unpackCreateTransferResult($data);

        $id = Uint128::fromParts($unpacked['timestamp'], 0);

        return new CreateTransferResult($id, CreateTransferStatus::from($unpacked['status']));
    }
}
