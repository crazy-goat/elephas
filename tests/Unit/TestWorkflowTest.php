<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\TestCase;

final class TestWorkflowTest extends TestCase
{
    private const WORKFLOW = __DIR__ . '/../../.github/workflows/tests.yaml';

    public function testWorkflowFileExists(): void
    {
        $this->assertFileExists(self::WORKFLOW, '.github/workflows/tests.yaml must exist');
    }

    public function testTriggersOnPushAndPr(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('push:', $content, 'workflow must trigger on push');
        $this->assertStringContainsString('pull_request:', $content, 'workflow must trigger on pull_request');
    }

    public function testRunsUnitTestSuite(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('--testsuite=unit', $content, 'workflow must run the unit test suite');
    }

    public function testRunsFunctionalTestSuite(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('--testsuite=functional', $content, 'workflow must run the functional test suite');
    }

    public function testBuildsNativeLibraryBeforeFunctionalTests(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('Build native tb_client library', $content, 'workflow must build tb_client before functional tests');
    }

    public function testNativeLibraryBuildUsesVersionPin(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('TB_VERSION: 0.17.4', $content, 'workflow must pin tb_client version');
    }

    public function testFunctionalSuiteHasTigerBeetleAddressEnv(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('TIGERBEETLE_ADDRESS', $content, 'workflow must set TIGERBEETLE_ADDRESS for functional tests');
    }

    public function testFunctionalSuiteRunStepIsDistinct(): void
    {
        $content = $this->getContent();

        $this->assertStringContainsString('Run functional tests', $content, 'workflow must have a distinct step for functional tests');
    }

    public function testNoTrailingWhitespace(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[ \t]+$/m', $this->getContent(), 'test workflow must not contain trailing whitespace');
    }

    public function testEndsWithNewline(): void
    {
        $this->assertStringEndsWith("\n", $this->getContent(), 'test workflow file must end with a newline');
    }

    private function getContent(): string
    {
        $this->assertFileExists(self::WORKFLOW);
        $content = file_get_contents(self::WORKFLOW);
        $this->assertIsString($content);

        return $content;
    }
}
