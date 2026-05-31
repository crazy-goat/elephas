<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Backend;

/**
 * Abstract base class for TigerBeetle backends.
 *
 * Provides common functionality for all backend implementations.
 */
abstract class AbstractBackend implements BackendInterface
{
    protected bool $closed = false;

    /**
     * Check if the backend is closed.
     */
    protected function ensureNotClosed(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Backend is closed');
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
