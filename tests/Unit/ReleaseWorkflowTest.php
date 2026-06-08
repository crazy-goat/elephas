<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    private const WORKFLOW = __DIR__ . '/../../.github/workflows/release.yaml';

    public function testWorkflowFileExists(): void
    {
        $this->assertFileExists(self::WORKFLOW, '.github/workflows/release.yaml must exist');
    }

    public function testTriggersOnTagPush(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('tags:', $content, 'workflow must filter on tags');
        $this->assertStringContainsString("- 'v*'", $content, 'workflow must trigger on v* tags');
    }

    public function testHasContentWritePermission(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('contents: write', $content, 'workflow must request contents: write permission to publish the release');
    }

    public function testHasBuildLibsJob(): void
    {
        $this->assertStringContainsString('build-libs:', $this->getContent(), 'workflow must define a build-libs job');
    }

    public function testHasReleaseJob(): void
    {
        $this->assertStringContainsString('release:', $this->getContent(), 'workflow must define a release job');
    }

    public function testReleaseJobDependsOnBuildLibs(): void
    {
        $content = $this->getContent();
        $releaseBlock = $this->extractJob($content, 'release:');

        $this->assertStringContainsString('needs: build-libs', $releaseBlock, 'release job must depend on build-libs so it runs after the libs are built');
    }

    public function testBuildLibsMatrixCoversFourTargetPlatforms(): void
    {
        $content = $this->getContent();
        $matrixEntries = $this->extractMatrixEntries($content);

        $expectedPlatforms = [
            'linux-amd64' => [
                'asset-name' => 'libtb_client-x86_64-linux-gnu.so',
                'release-dir' => 'x86_64-linux-gnu',
            ],
            'linux-arm64' => [
                'asset-name' => 'libtb_client-aarch64-linux-gnu.so',
                'release-dir' => 'aarch64-linux-gnu',
            ],
            'macos-amd64' => [
                'asset-name' => 'libtb_client-x86_64-macos.dylib',
                'release-dir' => 'x86_64-macos',
            ],
            'macos-arm64' => [
                'asset-name' => 'libtb_client-aarch64-macos.dylib',
                'release-dir' => 'aarch64-macos',
            ],
        ];

        $actual = [];
        foreach ($matrixEntries as $entry) {
            $this->assertArrayHasKey('platform', $entry, 'each matrix entry must declare a platform');
            $this->assertArrayHasKey('release-dir', $entry, 'each matrix entry must declare a release-dir');
            $this->assertArrayHasKey('os', $entry, 'each matrix entry must declare a runner os');
            $this->assertArrayHasKey('ext', $entry, 'each matrix entry must declare a library extension (so/dylib)');
            $this->assertArrayHasKey('asset-name', $entry, 'each matrix entry must declare the release asset name');
            $platform = $this->stringField($entry, 'platform');
            $actual[$platform] = [
                'asset-name' => $this->stringField($entry, 'asset-name'),
                'release-dir' => $this->stringField($entry, 'release-dir'),
            ];
        }

        $this->assertEqualsCanonicalizing(
            array_keys($expectedPlatforms),
            array_keys($actual),
            'build matrix must cover all four supported target platforms',
        );

        foreach ($expectedPlatforms as $platform => $expected) {
            $this->assertSame(
                $expected['asset-name'],
                $actual[$platform]['asset-name'],
                "platform {$platform} must produce asset {$expected['asset-name']}",
            );
            $this->assertSame(
                $expected['release-dir'],
                $actual[$platform]['release-dir'],
                "platform {$platform} must use release-dir {$expected['release-dir']}",
            );
        }
    }

    public function testMatrixExtensionsMatchPlatformFamilies(): void
    {
        $content = $this->getContent();
        $matrixEntries = $this->extractMatrixEntries($content);

        foreach ($matrixEntries as $entry) {
            $platform = $entry['platform'] ?? '';
            $ext = $entry['ext'] ?? '';
            if (str_starts_with($platform, 'linux-')) {
                $this->assertSame('so', $ext, "linux platform {$platform} must use .so extension");
            } elseif (str_starts_with($platform, 'macos-')) {
                $this->assertSame('dylib', $ext, "macos platform {$platform} must use .dylib extension");
            } else {
                $this->fail("unknown platform family: {$platform}");
            }
        }
    }

    public function testBuildLibsJobInvokesBuildScript(): void
    {
        $content = $this->getContent();
        $jobBlock = $this->extractJob($content, 'build-libs:');

        $this->assertStringContainsString('bash bin/build-tb-client.sh', $jobBlock, 'build-libs job must invoke bin/build-tb-client.sh');
    }

    public function testBuildLibsJobStagingCpUsesReleaseDir(): void
    {
        $content = $this->getContent();
        $matrixEntries = $this->extractMatrixEntries($content);
        $jobBlock = $this->extractJob($content, 'build-libs:');

        // The cp source path must use release-dir matrix variable, not the short platform name
        $this->assertStringContainsString(
            'resources/lib/${{ matrix.release-dir }}',
            $jobBlock,
            'build-libs job must cp from resources/lib/${{ matrix.release-dir }}',
        );

        // The cp destination must use asset-name matrix variable
        $this->assertStringContainsString(
            '${{ matrix.asset-name }}',
            $jobBlock,
            'build-libs job must cp to ${{ matrix.asset-name }}',
        );

        // Each platform must define release-dir mapping
        foreach ($matrixEntries as $entry) {
            $platform = $entry['platform'] ?? '';
            $releaseDir = $entry['release-dir'] ?? '';
            $this->assertNotEmpty($releaseDir, "platform {$platform} must define release-dir");
            $this->assertNotEmpty($entry['ext'] ?? '', "platform {$platform} must define ext");
            $this->assertNotEmpty($entry['asset-name'] ?? '', "platform {$platform} must define asset-name");
        }
    }

    public function testBuildLibsJobUploadsArtifact(): void
    {
        $content = $this->getContent();
        $jobBlock = $this->extractJob($content, 'build-libs:');

        $this->assertStringContainsString('actions/upload-artifact@v4', $jobBlock, 'build-libs job must upload artifacts via actions/upload-artifact@v4');
        $this->assertStringContainsString('if-no-files-found: error', $jobBlock, 'artifact upload must fail if the staged asset is missing');
    }

    public function testReleaseJobDownloadsAllArtifacts(): void
    {
        $content = $this->getContent();
        $jobBlock = $this->extractJob($content, 'release:');

        $this->assertStringContainsString('actions/download-artifact@v4', $jobBlock, 'release job must download artifacts via actions/download-artifact@v4');
        $this->assertStringContainsString('merge-multiple: true', $jobBlock, 'release job must set merge-multiple: true so all build artifacts land flat in the destination directory');
        $this->assertStringContainsString('pattern: tb_client-*', $jobBlock, 'release job must filter artifacts to the tb_client-* pattern so unrelated artifacts are not pulled into the release');
    }

    public function testReleaseJobUsesGhReleaseAction(): void
    {
        $content = $this->getContent();
        $jobBlock = $this->extractJob($content, 'release:');

        $this->assertStringContainsString('softprops/action-gh-release@v2', $jobBlock, 'release job must use softprops/action-gh-release@v2');
        $this->assertStringContainsString('generate_release_notes: true', $jobBlock, 'release job must auto-generate release notes');
    }

    public function testReleaseJobListsAllExpectedAssetFiles(): void
    {
        $content = $this->getContent();
        $jobBlock = $this->extractJob($content, 'release:');

        $expectedAssets = [
            'artifacts/libtb_client-x86_64-linux-gnu.so',
            'artifacts/libtb_client-aarch64-linux-gnu.so',
            'artifacts/libtb_client-x86_64-macos.dylib',
            'artifacts/libtb_client-aarch64-macos.dylib',
        ];

        foreach ($expectedAssets as $asset) {
            $this->assertStringContainsString(
                $asset,
                $jobBlock,
                "release job must list {$asset} as a release asset",
            );
        }
    }

    public function testNoTrailingWhitespace(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[ \t]+$/m', $this->getContent(), 'release workflow must not contain trailing whitespace');
    }

    public function testEndsWithNewline(): void
    {
        $this->assertStringEndsWith("\n", $this->getContent(), 'release workflow file must end with a newline');
    }

    private function getContent(): string
    {
        $this->assertFileExists(self::WORKFLOW);
        $content = file_get_contents(self::WORKFLOW);
        $this->assertIsString($content);

        return $content;
    }

    private function extractJob(string $content, string $header): string
    {
        $pos = strpos($content, $header);
        $this->assertNotFalse($pos, "job header '{$header}' not found");

        $start = $pos + strlen($header);
        $tail = substr($content, $start);

        $end = strlen($tail);
        foreach (['  release:', '  build-libs:'] as $next) {
            $candidate = strpos($tail, "\n" . $next);
            if ($candidate !== false && $candidate < $end) {
                $end = $candidate;
            }
        }

        return substr($tail, 0, $end);
    }

    /**
     * @return list<array{os?: string, platform?: string, ext?: string, asset-name?: string}>
     */
    private function extractMatrixEntries(string $content): array
    {
        $matrixStart = strpos($content, 'matrix:');
        $this->assertNotFalse($matrixStart, 'matrix: not found in workflow');

        $includeStart = strpos($content, 'include:', $matrixStart);
        $this->assertNotFalse($includeStart, 'matrix.include: not found in workflow');

        $tail = substr($content, $includeStart + strlen('include:'));
        $tailLen = strlen($tail);

        $matrixEnd = $tailLen;
        $nextJob = strpos($tail, "\n    steps:");
        if ($nextJob !== false && $nextJob < $matrixEnd) {
            $matrixEnd = $nextJob;
        }
        $matrixBlock = substr($tail, 0, $matrixEnd);

        $entries = [];
        $lines = explode("\n", $matrixBlock);
        $current = null;
        foreach ($lines as $line) {
            if (preg_match('/^\s*-\s+(\S+):\s*(.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [$m[1] => rtrim($m[2])];
            } elseif ($current !== null && preg_match('/^\s+(\S+):\s*(.*)$/', $line, $m)) {
                $current[$m[1]] = rtrim($m[2]);
            }
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * @param array<string, string|null> $entry
     */
    private function stringField(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;
        $this->assertIsString($value, "matrix entry field '{$key}' must be a string");
        $this->assertNotSame('', $value, "matrix entry field '{$key}' must not be empty");

        return $value;
    }
}
