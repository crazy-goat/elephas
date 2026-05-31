<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Batch;

/**
 * Abstract base class for TigerBeetle batch operations.
 *
 * Provides navigation and state management for batch items.
 * Concrete implementations handle specific data types.
 */
abstract class AbstractBatch implements \Countable
{
    protected int $position = 0;

    protected int $length = 0;

    /**
     * TODO: implement
     */
    public function __construct(
        protected readonly int $capacity,
    ) {
    }

    /**
     * Add a new item to the batch.
     */
    abstract public function add(): void;

    /**
     * Move to the next item.
     */
    public function next(): bool
    {
        // TODO: implement
        return false;
    }

    /**
     * Move to the previous item.
     */
    public function prev(): bool
    {
        // TODO: implement
        return false;
    }

    /**
     * Reset position to the beginning.
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Get the current number of items in the batch.
     *
     * @return int<0, max>
     */
    public function getLength(): int
    {
        /** @phpstan-ignore return.type */
        return $this->length;
    }

    /**
     * Get the maximum capacity of the batch.
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Check if the current position is valid.
     */
    public function isValidPosition(): bool
    {
        return $this->position >= 0 && $this->position < $this->length;
    }

    /**
     * Check if this batch is read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
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
