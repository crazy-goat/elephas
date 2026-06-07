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
        $length = \strlen($buffer);
        $structSize = BinaryHelper::CREATE_ACCOUNT_RESULT_SIZE;
        if ($length % $structSize !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'CreateAccountResultBatch buffer size must be a multiple of %d bytes, got %d bytes',
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

    public function getResult(): CreateAccountResult
    {
        $this->requireValidPosition('read field');
        $offset = $this->currentPosition * $this->getStructSize();
        $data = \substr($this->buffer, $offset, $this->getStructSize());
        $unpacked = BinaryHelper::unpackCreateAccountResult($data);

        try {
            return new CreateAccountResult($unpacked['timestamp'], CreateAccountStatus::from($unpacked['status']));
        } catch (\ValueError) {
            throw UnknownStatusException::forEnum(CreateAccountStatus::class, $unpacked['status']);
        }
    }
}
