<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

abstract class AbstractBatch implements \Countable
{
    protected string $buffer;

    protected int $length = 0;

    protected int $currentPosition = 0;

    public function __construct(
        protected readonly int $capacity,
    ) {
        $this->buffer = \str_repeat("\0", $capacity * $this->getStructSize());
    }

    abstract protected function getStructSize(): int;

    public function add(): void
    {
        if ($this->length >= $this->capacity) {
            throw new \OverflowException('Batch capacity exceeded');
        }

        $this->currentPosition = $this->length;
        $this->length++;
    }

    public function next(): bool
    {
        if ($this->currentPosition >= $this->length - 1) {
            return false;
        }

        $this->currentPosition++;

        return true;
    }

    public function prev(): bool
    {
        if ($this->currentPosition <= 0) {
            return false;
        }

        $this->currentPosition--;

        return true;
    }

    public function rewind(): void
    {
        $this->currentPosition = 0;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function isValidPosition(): bool
    {
        return $this->currentPosition >= 0 && $this->currentPosition < $this->length;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getBuffer(): string
    {
        $size = $this->length * $this->getStructSize();

        return $size > 0 ? \substr($this->buffer, 0, $size) : '';
    }

    public function toBytes(): string
    {
        return $this->getBuffer();
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        /** @phpstan-ignore return.type */
        return $this->length;
    }
}
