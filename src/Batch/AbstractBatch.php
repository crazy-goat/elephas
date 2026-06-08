<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

use CrazyGoat\Elephas\Exception\InvalidBatchCursorException;
use CrazyGoat\Elephas\Uint128\Uint128;

abstract class AbstractBatch implements \Countable
{
    /** @var array<int, string> Per-struct buffers (one zero-filled string per struct slot) */
    protected array $buffers = [];

    protected int $length = 0;

    protected int $currentPosition = 0;

    public function __construct(
        protected readonly int $capacity,
    ) {
    }

    abstract protected function getStructSize(): int;

    public function add(): void
    {
        if ($this->length >= $this->capacity) {
            throw new \OverflowException('Batch capacity exceeded');
        }

        $this->buffers[$this->length] = \str_repeat("\0", $this->getStructSize());
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

    /**
     * @throws InvalidBatchCursorException when the current position is outside the populated range
     */
    protected function requireValidPosition(string $action): void
    {
        if (!$this->isValidPosition()) {
            throw InvalidBatchCursorException::atPosition(
                static::class,
                $this->currentPosition,
                $this->length,
                $action,
            );
        }
    }

    public function getBuffer(): string
    {
        if ($this->length === 0) {
            return '';
        }

        $parts = [];
        for ($i = 0; $i < $this->length; $i++) {
            $parts[] = $this->buffers[$i] ?? \str_repeat("\0", $this->getStructSize());
        }

        return \implode('', $parts);
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

    // ──────────────────────────────────────────────
    //  Protected read/write helpers for subclasses
    // ──────────────────────────────────────────────

    protected function getBufferAtPosition(int $position): string
    {
        return $this->buffers[$position] ?? \str_repeat("\0", $this->getStructSize());
    }

    protected function readUint128(int $fieldOffset): Uint128
    {
        $this->requireValidPosition('read field');
        $struct = $this->getBufferAtPosition($this->currentPosition);

        return Uint128::fromBytes(\substr($struct, $fieldOffset, 16));
    }

    protected function writeUint128(int $fieldOffset, Uint128 $value): void
    {
        $this->requireValidPosition('write field');
        $this->buffers[$this->currentPosition] = \substr_replace(
            $this->getBufferAtPosition($this->currentPosition),
            $value->toBytes(),
            $fieldOffset,
            16,
        );
    }

    protected function readUint64(int $fieldOffset): int
    {
        $this->requireValidPosition('read field');
        $struct = $this->getBufferAtPosition($this->currentPosition);
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('P', \substr($struct, $fieldOffset, 8));

        return $unpacked[1];
    }

    protected function writeUint64(int $fieldOffset, int $value): void
    {
        $this->requireValidPosition('write field');
        $this->buffers[$this->currentPosition] = \substr_replace(
            $this->getBufferAtPosition($this->currentPosition),
            \pack('P', $value),
            $fieldOffset,
            8,
        );
    }

    protected function readUint32(int $fieldOffset): int
    {
        $this->requireValidPosition('read field');
        $struct = $this->getBufferAtPosition($this->currentPosition);
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('V', \substr($struct, $fieldOffset, 4));

        return $unpacked[1];
    }

    protected function writeUint32(int $fieldOffset, int $value): void
    {
        $this->requireValidPosition('write field');
        $this->buffers[$this->currentPosition] = \substr_replace(
            $this->getBufferAtPosition($this->currentPosition),
            \pack('V', $value),
            $fieldOffset,
            4,
        );
    }

    protected function readUint16(int $fieldOffset): int
    {
        $this->requireValidPosition('read field');
        $struct = $this->getBufferAtPosition($this->currentPosition);
        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('v', \substr($struct, $fieldOffset, 2));

        return $unpacked[1];
    }

    protected function writeUint16(int $fieldOffset, int $value): void
    {
        $this->requireValidPosition('write field');
        $this->buffers[$this->currentPosition] = \substr_replace(
            $this->getBufferAtPosition($this->currentPosition),
            \pack('v', $value),
            $fieldOffset,
            2,
        );
    }

    /**
     * Load batch from an existing binary buffer.
     *
     * @param string $buffer The binary data (should be a multiple of struct size)
     */
    protected static function fromBufferInternal(string $buffer, int $structSize): static
    {
        $length = \strlen($buffer);
        $className = static::class;
        $shortName = \substr($className, \strrpos($className, '\\') + 1);
        if ($length > 0 && $length % $structSize !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                '%s buffer size must be a multiple of %d bytes, got %d bytes',
                $shortName,
                $structSize,
                $length,
            ));
        }
        $count = $length > 0 ? \intdiv($length, $structSize) : 0;
        /** @phpstan-ignore new.static */
        $batch = new static($count);
        if ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                $batch->buffers[$i] = \substr($buffer, $i * $structSize, $structSize);
            }
            $batch->length = $count;
        }

        return $batch;
    }
}
