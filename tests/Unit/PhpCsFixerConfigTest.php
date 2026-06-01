<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\TestCase;

class PhpCsFixerConfigTest extends TestCase
{
    private const CONFIG_FILE = __DIR__ . '/../../.php-cs-fixer.dist.php';

    private function getConfigContent(): string
    {
        $this->assertFileExists(self::CONFIG_FILE);
        $content = file_get_contents(self::CONFIG_FILE);
        $this->assertIsString($content);

        return $content;
    }

    public function testConfigFileExists(): void
    {
        $this->assertFileExists(self::CONFIG_FILE);
    }

    public function testConfigFileIsPhpFile(): void
    {
        $this->assertStringEndsWith('.php', self::CONFIG_FILE);
    }

    public function testConfigReturnsPhpCsFixerConfig(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString('new PhpCsFixer\Config()', $content);
    }

    public function testDeclareStrictTypesIsPresent(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("declare(strict_types=1);", $content);
    }

    public function testStrictComparisonRuleIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'strict_comparison' => true", $content);
    }

    public function testDeclareStrictTypesRuleIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'declare_strict_types' => true", $content);
    }

    public function testCacheFileIsSetToVarDir(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'/var/.php-cs-fixer.cache'", $content);
    }

    public function testPerCs2x0RulesetIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'@PER-CS2x0' => true", $content);
    }

    public function testPerCs2x0RiskyRulesetIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'@PER-CS2x0:risky' => true", $content);
    }

    public function testRiskyAllowedIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("->setRiskyAllowed(true)", $content);
    }

    public function testUnsupportedPhpVersionAllowedIsEnabled(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("->setUnsupportedPhpVersionAllowed(true)", $content);
    }

    public function testFinderIncludesSrcDir(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'/src'", $content);
    }

    public function testFinderIncludesTestsDir(): void
    {
        $content = $this->getConfigContent();
        $this->assertStringContainsString("'/tests'", $content);
    }

    public function testDryRunPasses(): void
    {
        $command = 'vendor/bin/php-cs-fixer fix -v --dry-run 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, 'PHP-CS-Fixer dry-run failed: ' . implode("\n", $output));
    }
}
