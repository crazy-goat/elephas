<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DockerImageTest extends TestCase
{
    private const DOCKERFILE = __DIR__ . '/../../docker/Dockerfile';

    public function testDoesNotDependOnBuildkitPlatformArguments(): void
    {
        // TARGETOS and TARGETARCH are automatic platform arguments that only
        // BuildKit injects. The classic builder leaves them empty, so a download
        // guarded by them silently does nothing and the later COPY fails with
        // "stat usr/local/bin/tigerbeetle: file does not exist" (#211).
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*ARG TARGET(OS|ARCH)\b/m',
            $this->getContent(),
            'docker/Dockerfile must not read the BuildKit-only automatic arguments TARGETOS/TARGETARCH',
        );
    }

    public function testDerivesThePlatformInsideTheBuildStage(): void
    {
        $this->assertStringContainsString(
            'uname -m',
            $this->getTigerbeetleDownloadStep(),
            'the tigerbeetle-build stage must derive the platform with uname -m',
        );
    }

    public function testCopiesTheBinaryFromTheTigerBeetleBuildStage(): void
    {
        $this->assertStringContainsString(
            'COPY --from=tigerbeetle-build /usr/local/bin/tigerbeetle /usr/local/bin/tigerbeetle',
            $this->getContent(),
            'the final stage must copy the binary downloaded by tigerbeetle-build',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function architectureProvider(): iterable
    {
        yield 'x86_64' => ['x86_64', 'x86_64-linux-gnu'];
        yield 'aarch64' => ['aarch64', 'aarch64-linux-gnu'];
    }

    #[DataProvider('architectureProvider')]
    public function testDownloadUrlMatchesTheArchitecture(string $uname, string $expected): void
    {
        [$exit, $output] = $this->runDownloadStep($uname);

        $this->assertSame(0, $exit, "the download step must succeed on $uname: $output");
        $this->assertStringContainsString(
            'https://linux.tigerbeetle.com/tigerbeetle-' . $this->getTbVersion() . '-' . $expected . '.zip',
            $output,
            'the download URL must match the architecture reported by uname',
        );
    }

    public function testUnsupportedArchitectureFailsWithAClearMessage(): void
    {
        [$exit, $output] = $this->runDownloadStep('mips64');

        $this->assertNotSame(0, $exit, 'an unsupported architecture must fail the build');
        $this->assertStringContainsString('Unsupported architecture: mips64', $output);
    }

    public function testDownloadFailsOnHttpError(): void
    {
        $this->assertStringContainsString(
            'curl -fLo',
            $this->getTigerBeetleDownloadStep(),
            'curl must use -f, otherwise an HTTP error page is saved as the binary',
        );
    }

    /**
     * Runs the download step of the tigerbeetle-build stage in a shell whose
     * PATH contains nothing but the stub directory. That checks the
     * architecture mapping and the failure path without Docker, a network
     * connection or a real download, and any other command the step may call
     * is simply not found instead of running for real.
     *
     * @return array{int, string} the exit status and everything the stubs printed
     */
    private function runDownloadStep(string $uname): array
    {
        $dir = \sys_get_temp_dir() . '/elephas-docker-image-test-' . \bin2hex(\random_bytes(6));
        $bin = $dir . '/bin';
        \mkdir($bin, 0o777, true);

        $log = $dir . '/curl.log';
        // Every command the step could call is a stub: curl records its
        // arguments (the URL is the only one starting with https) instead of
        // downloading, and unzip and rm do nothing.
        $stubs = [
            'uname' => "#!/bin/sh\necho $uname\n",
            'curl' => "#!/bin/sh\nprintf '%s\\n' \"\$@\" >> " . \escapeshellarg($log) . "\n",
            'unzip' => "#!/bin/sh\nexit 0\n",
            'rm' => "#!/bin/sh\nexit 0\n",
        ];
        foreach ($stubs as $name => $body) {
            \file_put_contents($bin . '/' . $name, $body);
            \chmod($bin . '/' . $name, 0o755);
        }

        // The extracted RUN body is shell source, so it is appended unquoted;
        // only the stub directory needs quoting.
        $script = 'export PATH=' . \escapeshellarg($bin) . ' TB_VERSION=' . \escapeshellarg($this->getTbVersion()) . "\n"
            . $this->getTigerBeetleDownloadStep();
        $output = [];
        $exit = 0;
        \exec(\escapeshellarg('sh') . ' -c ' . \escapeshellarg($script) . ' 2>&1', $output, $exit);

        $text = \implode("\n", $output);
        if (\is_file($log)) {
            $logged = (string) \file_get_contents($log);
            $text .= "\n" . $logged;
            \unlink($log);
        }
        foreach (\glob($bin . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($bin);
        \rmdir($dir);

        return [$exit, $text];
    }

    /**
     * Returns the body of the first RUN instruction of the tigerbeetle-build
     * stage as a single shell line, with the continuations joined.
     */
    private function getTigerbeetleDownloadStep(): string
    {
        \preg_match('/^FROM base AS tigerbeetle-build$(.*?)(?=^FROM )/ms', $this->getContent(), $stage);
        $stageBody = $stage[1] ?? '';
        $this->assertNotSame(
            '',
            $stageBody,
            'docker/Dockerfile must have a tigerbeetle-build stage that is followed by the final stage',
        );

        \preg_match('/^RUN (.*?)(?<!\\\\)$/ms', $stageBody, $run);
        $runBody = $run[1] ?? '';
        $this->assertNotSame('', $runBody, 'the tigerbeetle-build stage must contain a RUN instruction');

        return \trim((string) \preg_replace('/\\\\\n\s*/', ' ', $runBody));
    }

    /**
     * Returns the default of ARG TB_VERSION, so a version bump in the
     * Dockerfile does not have to be repeated here.
     */
    private function getTbVersion(): string
    {
        $version = \preg_match('/^ARG TB_VERSION=(\S+)$/m', $this->getContent(), $match) === 1
            ? $match[1]
            : '';
        $this->assertNotSame('', $version, 'docker/Dockerfile must declare ARG TB_VERSION with a default');

        return $version;
    }

    private function getContent(): string
    {
        $this->assertFileExists(self::DOCKERFILE, 'docker/Dockerfile must exist');
        $content = \file_get_contents(self::DOCKERFILE);
        $this->assertIsString($content);

        return $content;
    }
}
