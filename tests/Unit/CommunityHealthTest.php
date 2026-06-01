<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CommunityHealthTest extends TestCase
{
    private string $githubDir;

    protected function setUp(): void
    {
        $this->githubDir = \dirname(__DIR__, 2) . '/.github';
    }

    public function testSecurityPolicyFileExists(): void
    {
        $this->assertFileExists($this->githubDir . '/SECURITY.md');
    }

    public function testSecurityPolicyHasVulnerabilityReporting(): void
    {
        $content = $this->getContent('SECURITY.md');
        $this->assertStringContainsString('Reporting a Vulnerability', $content);
    }

    public function testSecurityPolicyHasSupportedVersions(): void
    {
        $content = $this->getContent('SECURITY.md');
        $this->assertStringContainsString('Supported Versions', $content);
    }

    public function testSecurityPolicyHasEmail(): void
    {
        $content = $this->getContent('SECURITY.md');
        $this->assertStringContainsString('halaspiotr@gmail.com', $content);
    }

    public function testSecurityPolicyDoNotOpenPublicIssue(): void
    {
        $content = $this->getContent('SECURITY.md');
        $this->assertStringContainsString('Do NOT open a public issue', $content);
    }

    public function testBugReportTemplateFileExists(): void
    {
        $this->assertFileExists($this->githubDir . '/ISSUE_TEMPLATE/bug_report.md');
    }

    public function testBugReportHasRequiredSections(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/bug_report.md');
        $this->assertStringContainsString('Description', $content);
        $this->assertStringContainsString('Steps to Reproduce', $content);
        $this->assertStringContainsString('Expected Behavior', $content);
        $this->assertStringContainsString('Actual Behavior', $content);
        $this->assertStringContainsString('Environment', $content);
    }

    public function testBugReportHasYamlFrontMatter(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/bug_report.md');
        $this->assertStringStartsWith('---', $content);
        $this->assertStringContainsString('name: Bug Report', $content);
        $this->assertStringContainsString('labels: bug', $content);
    }

    public function testFeatureRequestTemplateFileExists(): void
    {
        $this->assertFileExists($this->githubDir . '/ISSUE_TEMPLATE/feature_request.md');
    }

    public function testFeatureRequestHasRequiredSections(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/feature_request.md');
        $this->assertStringContainsString('Description', $content);
        $this->assertStringContainsString('Use Case', $content);
        $this->assertStringContainsString('Proposed Solution', $content);
        $this->assertStringContainsString('Alternatives Considered', $content);
    }

    public function testFeatureRequestHasYamlFrontMatter(): void
    {
        $content = $this->getContent('ISSUE_TEMPLATE/feature_request.md');
        $this->assertStringStartsWith('---', $content);
        $this->assertStringContainsString('name: Feature Request', $content);
        $this->assertStringContainsString('labels: enhancement', $content);
    }

    public function testPullRequestTemplateFileExists(): void
    {
        $this->assertFileExists($this->githubDir . '/PULL_REQUEST_TEMPLATE.md');
    }

    public function testPullRequestTemplateHasRequiredSections(): void
    {
        $content = $this->getContent('PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('Description', $content);
        $this->assertStringContainsString('Related Issues', $content);
        $this->assertStringContainsString('Type of Change', $content);
        $this->assertStringContainsString('Checklist', $content);
    }

    public function testPullRequestTemplateHasChecklistItems(): void
    {
        $content = $this->getContent('PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('PHPStan level 8 passes', $content);
        $this->assertStringContainsString('Unit tests added/updated', $content);
        $this->assertStringContainsString('CHANGELOG.md updated', $content);
    }

    public function testAllFilesEndWithNewline(): void
    {
        foreach (['SECURITY.md', 'ISSUE_TEMPLATE/bug_report.md', 'ISSUE_TEMPLATE/feature_request.md', 'PULL_REQUEST_TEMPLATE.md'] as $file) {
            $content = $this->getContent($file);
            $this->assertStringEndsWith("\n", $content, sprintf('File %s must end with newline', $file));
        }
    }

    public function testNoTrailingWhitespace(): void
    {
        foreach (['SECURITY.md', 'ISSUE_TEMPLATE/bug_report.md', 'ISSUE_TEMPLATE/feature_request.md', 'PULL_REQUEST_TEMPLATE.md'] as $file) {
            $content = $this->getContent($file);
            $this->assertDoesNotMatchRegularExpression('/[ \t]+$/m', $content, sprintf('File %s has trailing whitespace', $file));
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
