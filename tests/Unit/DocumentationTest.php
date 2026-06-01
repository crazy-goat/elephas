<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DocumentationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = \dirname(__DIR__, 2);
    }

    public function testContributingFileExists(): void
    {
        $this->assertFileExists($this->projectRoot . '/CONTRIBUTING.md');
    }

    public function testContributingHasDevelopmentSetup(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Development Setup', $content);
    }

    public function testContributingHasCodingStandards(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Coding Standards', $content);
    }

    public function testContributingHasTestingSection(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Testing', $content);
    }

    public function testContributingHasLintingSection(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Linting', $content);
    }

    public function testContributingHasBranchNaming(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Branch Naming', $content);
    }

    public function testContributingHasCommitMessageFormat(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Commit Message Format', $content);
    }

    public function testContributingHasPullRequestProcess(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Pull Request Process', $content);
    }

    public function testContributingHasTestCommands(): void
    {
        $content = $this->getContributingContent();
        $this->assertMatchesRegularExpression('/composer test(-unit|-functional)?/', $content);
    }

    public function testContributingHasLintCommands(): void
    {
        $content = $this->getContributingContent();
        $this->assertMatchesRegularExpression('/composer lint(-fix)?/', $content);
    }

    public function testContributingMentionsConventionalCommits(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('Conventional Commits', $content);
    }

    public function testContributingMentionsPrePushHook(): void
    {
        $content = $this->getContributingContent();
        $this->assertStringContainsString('pre-push', $content);
    }

    private function getContributingContent(): string
    {
        $path = $this->projectRoot . '/CONTRIBUTING.md';
        $this->assertFileExists($path);

        $content = \file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }
}
