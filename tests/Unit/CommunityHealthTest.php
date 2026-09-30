<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CommunityHealthTest extends TestCase
{
    private const FILES = [
        'ISSUE_TEMPLATE/bug.yml',
        'ISSUE_TEMPLATE/feature.yml',
        'ISSUE_TEMPLATE/config.yml',
        'pull_request_template.md',
    ];

    private string $githubDir;

    protected function setUp(): void
    {
        $this->githubDir = \dirname(__DIR__, 2) . '/.github';
    }

    public function testRepositoryDoesNotCarryItsOwnSecurityPolicy(): void
    {
        $this->assertFileDoesNotExist($this->githubDir . '/SECURITY.md');
    }

    public function testIssueConfigDisablesBlankIssuesAndLinksSecurityPolicy(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/config.yml');
        $this->assertStringContainsString('blank_issues_enabled: false', $content);
        $this->assertStringContainsString('crazy-goat/.github/blob/main/SECURITY.md', $content);
    }

    public function testBugFormHasRequiredFields(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/bug.yml');
        $this->assertStringContainsString('name: Bug report', $content);
        $this->assertStringContainsString('labels: ["type:bug"]', $content);
        $this->assertStringContainsString('label: Description', $content);
        $this->assertStringContainsString('label: Steps to reproduce', $content);
        $this->assertStringContainsString('label: Definition of done', $content);
        $this->assertStringContainsString('label: Version', $content);
    }

    public function testFeatureFormHasRequiredFields(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/feature.yml');
        $this->assertStringContainsString('name: Feature request', $content);
        $this->assertStringContainsString('labels: ["type:feature"]', $content);
        $this->assertStringContainsString('label: Description', $content);
        $this->assertStringContainsString('label: Definition of done', $content);
    }

    public function testPullRequestTemplateHasRequiredSections(): void
    {
        $content = $this->getContent('pull_request_template.md');
        $this->assertStringContainsString('## What changed', $content);
        $this->assertStringContainsString('Closes #', $content);
        $this->assertStringContainsString('## How it was tested', $content);
        $this->assertStringContainsString('## Checklist', $content);
    }

    public function testPullRequestTemplateHasChecklistItems(): void
    {
        $content = $this->getContent('pull_request_template.md');
        $this->assertStringContainsString('`CHANGELOG.md` updated under `[Unreleased]`', $content);
        $this->assertStringContainsString('Conventional Commit', $content);
        $this->assertStringContainsString('written in English', $content);
    }

    public function testAllFilesEndWithNewline(): void
    {
        foreach (self::FILES as $file) {
            $this->assertStringEndsWith("\n", $this->getContent($file), \sprintf('File %s must end with newline', $file));
        }
    }

    public function testNoTrailingWhitespace(): void
    {
        foreach (self::FILES as $file) {
            $this->assertDoesNotMatchRegularExpression('/[ \t]+$/m', $this->getContent($file), \sprintf('File %s has trailing whitespace', $file));
        }
    }

    private function getContent(string $relativePath): string
    {
        $path = $this->githubDir . '/' . $relativePath;
        $this->assertFileExists($path);

        $content = \file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }
}
