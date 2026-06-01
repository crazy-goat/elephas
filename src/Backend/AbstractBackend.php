<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

use CrazyGoat\Elephas\Exception\ClientClosedException;
use CrazyGoat\Elephas\Exception\TooMuchDataException;
use CrazyGoat\Elephas\Operation;

/**
 * Abstract base class for TigerBeetle backends.
 *
 * Provides common validation and uses the Template Method pattern.
 * Subclasses must implement doSubmit() and doClose().
 */
abstract class AbstractBackend implements BackendInterface
{
    private const DEFAULT_MAX_BATCH_SIZE = 1024 * 1024;

    protected bool $closed = false;

    public function submit(Operation $operation, string $data): string
    {
        $this->ensureNotClosed();

        $maxSize = $this->getMaxBatchSize();
        if (\strlen($data) > $maxSize) {
            throw TooMuchDataException::create($maxSize, \strlen($data));
        }

        return $this->doSubmit($operation, $data);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->doClose();
        $this->closed = true;
    }

    /**
     * Perform the actual submit operation.
     */
    abstract protected function doSubmit(Operation $operation, string $data): string;

    /**
     * Perform the actual cleanup when closing.
     */
    abstract protected function doClose(): void;

    /**
     * Get the maximum batch size in bytes.
     */
    protected function getMaxBatchSize(): int
    {
        return self::DEFAULT_MAX_BATCH_SIZE;
    }

    /**
     * Check if the backend is closed.
     *
     * @throws ClientClosedException if the backend is closed
     */
    protected function ensureNotClosed(): void
    {
        if ($this->closed) {
            throw ClientClosedException::create();
        }
    }
}
