<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Exception;

/**
 * Exception thrown when a native request does not complete within the
 * configured timeout window.
 *
 * Raised by {@see \CrazyGoat\Elephas\Backend\NativeClient::pollForCompletion()}
 * when the underlying `tb_client` packet stays in the pending state until the
 * timeout elapses. The exception carries the configured timeout in seconds so
 * callers can surface a meaningful diagnostic to their users.
 */
final class RequestTimeoutException extends \RuntimeException implements ElephasExceptionInterface
{
    private function __construct(private readonly float $timeoutSeconds)
    {
        parent::__construct(\sprintf(
            'TigerBeetle request timed out after %.3f s',
            $this->timeoutSeconds,
        ));
    }

    public static function create(float $timeoutSeconds): self
    {
        return new self($timeoutSeconds);
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }
}
