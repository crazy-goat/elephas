<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit\Backend;

use CrazyGoat\Elephas\Backend\NativeClient;

/**
 * Test-only subclass of {@see NativeClient} that exposes the readonly
 * `timeoutSeconds` property for controlled test scenarios.
 *
 * The parent constructor would attempt to load the `tb_client` shared
 * library, so the helper is constructed via reflection (see
 * {@see NativeClientTest::createClientWithoutFfi()}) and is never used
 * to drive real FFI calls. It deliberately does not override
 * `pollForCompletion()` or `processCompletionResult()` so the parent
 * code paths can be exercised.
 */
final class TimeoutSettableNativeClient extends NativeClient
{
    public function setTimeoutForTests(float $timeoutSeconds): void
    {
        $ref = new \ReflectionProperty(NativeClient::class, 'timeoutSeconds');
        $ref->setValue($this, $timeoutSeconds);
    }
}
