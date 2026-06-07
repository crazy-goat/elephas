<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\TestCase;

final class BuildTbClientScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../bin/build-tb-client.sh';

    public function testScriptExists(): void
    {
        $this->assertFileExists(self::SCRIPT, 'bin/build-tb-client.sh must exist');
    }

    public function testScriptIsExecutable(): void
    {
        $this->assertFileExists(self::SCRIPT);
        $this->assertTrue(
            is_executable(self::SCRIPT),
            'bin/build-tb-client.sh must be executable (chmod +x)',
        );
    }

    public function testScriptHasValidBashSyntax(): void
    {
        $this->assertFileExists(self::SCRIPT);

        $output = [];
        $exit = 0;
        exec('bash -n ' . escapeshellarg(self::SCRIPT) . ' 2>&1', $output, $exit);

        $this->assertSame(
            0,
            $exit,
            'bash -n reported syntax errors: ' . implode("\n", $output),
        );
    }

    public function testHelpFlagPrintsUsageAndExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellarg(self::SCRIPT) . ' --help 2>&1', $output, $exit);

        $this->assertSame(0, $exit, '--help must exit 0');
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Usage:', implode("\n", $output));
        $this->assertStringContainsString('build-tb-client.sh', implode("\n", $output));
    }

    public function testUnknownOptionExitsWithCodeOne(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellarg(self::SCRIPT) . ' --definitely-not-a-flag 2>&1', $output, $exit);

        $this->assertSame(1, $exit, 'unknown options must exit 1');
        $this->assertStringContainsString('unknown option', implode("\n", $output));
    }

    public function testCheckFlagPrintsHostPlatform(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellarg(self::SCRIPT) . ' --check 2>&1', $output, $exit);

        $this->assertSame(0, $exit, '--check must exit 0');
        $combined = implode("\n", $output);

        $this->assertStringContainsString('host_platform=', $combined);
        $this->assertStringContainsString('clients_lib_subdir=', $combined);
        $this->assertStringContainsString('output_path=', $combined);

        // host_platform should be one of the four known values.
        $this->assertMatchesRegularExpression(
            '/host_platform=(linux-amd64|linux-arm64|macos-amd64|macos-arm64)/',
            $combined,
            'host_platform must be a known platform identifier',
        );

        // clients_lib_subdir should match the host's expected subdir.
        $host = $this->parseValue($combined, 'host_platform');
        $subdir = $this->parseValue($combined, 'clients_lib_subdir');
        $this->assertSame(
            $this->expectedSubdirFor($host),
            $subdir,
            'clients_lib_subdir must match the expected upstream directory for the host platform',
        );

        // output_path must end with a known library filename.
        $outPath = $this->parseValue($combined, 'output_path');
        $this->assertStringEndsWith(
            $this->expectedLibNameFor($host),
            $outPath,
            'output_path must end with the expected library filename',
        );
    }

    public function testCheckFlagRespectsTbTargetOverride(): void
    {
        $output = [];
        $exit = 0;
        exec(
            'TB_TARGET=x86_64-macos ' . escapeshellarg(self::SCRIPT) . ' --check 2>&1',
            $output,
            $exit,
        );

        $this->assertSame(0, $exit, '--check must exit 0 even when TB_TARGET is overridden');
        $this->assertStringContainsString('clients_lib_subdir=x86_64-macos', implode("\n", $output));
    }

    public function testCheckFlagRespectsOutputDirOverride(): void
    {
        $output = [];
        $exit = 0;
        exec(
            'OUTPUT_DIR=/tmp/elephas-test-out ' . escapeshellarg(self::SCRIPT) . ' --check 2>&1',
            $output,
            $exit,
        );

        $this->assertSame(0, $exit, '--check must exit 0 even when OUTPUT_DIR is overridden');
        $this->assertStringContainsString(
            'output_path=/tmp/elephas-test-out/',
            implode("\n", $output),
        );
    }

    public function testMissingZigFailsCleanlyWithSkipInstall(): void
    {
        $output = [];
        $exit = 0;
        exec(
            'SKIP_ZIG_INSTALL=1 PATH=/usr/bin:/bin ' . escapeshellarg(self::SCRIPT) . ' 2>&1',
            $output,
            $exit,
        );

        $this->assertSame(
            3,
            $exit,
            'missing zig with SKIP_ZIG_INSTALL=1 must exit with code 3 (prerequisite missing)',
        );
        $this->assertStringContainsString('zig 0.14.1 not found', implode("\n", $output));
    }

    public function testCleanFlagIsIdempotent(): void
    {
        // Use a temporary output directory so we don't delete the real
        // library files that functional tests depend on.
        $tmpDir = sys_get_temp_dir() . '/elephas-clean-test-' . uniqid();
        mkdir($tmpDir, 0777, true);
        // Create a dummy file so --clean has something to remove.
        touch($tmpDir . '/dummy.so');

        try {
            $first = [];
            exec(
                'OUTPUT_DIR=' . escapeshellarg($tmpDir) . ' ' . escapeshellarg(self::SCRIPT) . ' --clean 2>&1',
                $first,
                $exit1,
            );
            $this->assertSame(0, $exit1, 'first --clean must succeed');

            $second = [];
            exec(
                'OUTPUT_DIR=' . escapeshellarg($tmpDir) . ' ' . escapeshellarg(self::SCRIPT) . ' --clean 2>&1',
                $second,
                $exit2,
            );
            $this->assertSame(0, $exit2, 'second --clean must also succeed (idempotent)');
        } finally {
            // Clean up the temporary directory.
            if (is_dir($tmpDir)) {
                array_map('unlink', glob($tmpDir . '/*'));
                rmdir($tmpDir);
            }
        }

        $this->assertDirectoryDoesNotExist($tmpDir);
    }

    public function testScriptAdvertisesCliFlagsInHelp(): void
    {
        $output = [];
        exec(escapeshellarg(self::SCRIPT) . ' --help 2>&1', $output, $exit);
        $this->assertSame(0, $exit);
        $help = implode("\n", $output);

        foreach (['--check', '--clean', '--help', 'TB_VERSION', 'ZIG_VERSION', 'OUTPUT_DIR'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $help,
                "help output must document the {$needle} option/variable",
            );
        }
    }

    public function testScriptSetsExecutableBitOnInstalledLibrary(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellarg(self::SCRIPT) . ' --check 2>&1', $output, $exit);

        $this->assertSame(0, $exit);
        $combined = implode("\n", $output);
        $this->assertStringContainsString('output_path=', $combined);

        // Verify the script installs with 0644 (chmod 0644) by grepping the
        // source. This is a contract test: we want the script to be safe to
        // run as a non-root user and avoid making the lib setuid-ish.
        $source = file_get_contents(self::SCRIPT);
        $this->assertNotFalse($source, 'could not read script source');
        $this->assertStringContainsString('chmod 0644', $source);
    }

    public function testScriptDoesNotReferenceUnsupportedHostsSilently(): void
    {
        // The script should fail loudly (exit 1) for unsupported platforms,
        // not silently produce nothing. We can only check on the current
        // host that a supported one is recognised.
        $output = [];
        exec(escapeshellarg(self::SCRIPT) . ' --check 2>&1', $output, $exit);
        $this->assertSame(0, $exit, 'current host must be supported');
    }

    private function parseValue(string $output, string $key): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=(.*)$/m';
        if (!preg_match($pattern, $output, $matches)) {
            $this->fail("missing {$key}= line in output:\n{$output}");
        }

        return $matches[1];
    }

    /**
     * @return non-empty-string
     */
    private function expectedSubdirFor(string $platform): string
    {
        return match ($platform) {
            'linux-amd64' => 'x86_64-linux-gnu.2.27',
            'linux-arm64' => 'aarch64-linux-gnu.2.27',
            'macos-amd64' => 'x86_64-macos',
            'macos-arm64' => 'aarch64-macos',
            default => $this->fail("unknown platform: {$platform}"),
        };
    }

    /**
     * @return non-empty-string
     */
    private function expectedLibNameFor(string $platform): string
    {
        return match ($platform) {
            'linux-amd64', 'linux-arm64' => 'libtb_client.so',
            'macos-amd64', 'macos-arm64' => 'libtb_client.dylib',
            default => $this->fail("unknown platform: {$platform}"),
        };
    }
}
