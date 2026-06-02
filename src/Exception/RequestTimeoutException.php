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
    public static function create(float $timeoutSeconds): self
    {
        return new self(\sprintf(
            'TigerBeetle request timed out after %.3f s',
            $timeoutSeconds,
        ));
    }

    public function getTimeoutSeconds(): float
    {
        $message = $this->getMessage();

        if (\preg_match('/after (\d+(?:\.\d+)?) s/', $message, $matches) === 1) {
            return (float) $matches[1];
        }

        return 0.0;
    }
}
