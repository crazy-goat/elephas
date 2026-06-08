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

    public function testReadmeFileExists(): void
    {
        $this->assertFileExists($this->projectRoot . '/README.md');
    }

    public function testReadmeHasClientLifecycleSection(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Client Lifecycle and Concurrency', $content);
    }

    public function testReadmeHasRequestTimeoutSection(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Request Timeout', $content);
    }

    public function testReadmeHasCloseDocumentation(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('close()', $content);
    }

    public function testReadmeMentionsConcurrency(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Concurrency', $content);
    }

    public function testReadmeMentionsClientClosedException(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('ClientClosedException', $content);
    }

    public function testReadmeHasCreateOperationResultsSection(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Create Operation Results', $content);
    }

    public function testReadmeExplainsPositionalCorrespondence(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('positional', $content);
    }

    public function testReadmeDocumentsIsCreated(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('isCreated()', $content);
    }

    public function testReadmeDocumentsGetTimestamp(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('getTimestamp()', $content);
    }

    public function testReadmeDocumentsGetStatus(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('getStatus()', $content);
    }

    public function testReadmeDocumentsPartialFailure(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Partial Failure', $content);
    }

    public function testReadmeDocumentsLinkedEvents(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Linked Events', $content);
    }

    public function testReadmeDocumentsLinkedEventChainOpen(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('LINKED_EVENT_CHAIN_OPEN', $content);
    }

    public function testReadmeDocumentsLinkedEventFailed(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('LINKED_EVENT_FAILED', $content);
    }

    public function testReadmeHasResultSemanticsSummaryTable(): void
    {
        $content = $this->getReadmeContent();
        $this->assertStringContainsString('Result Semantics Summary', $content);
    }

    private function getContributingContent(): string
    {
        $path = $this->projectRoot . '/CONTRIBUTING.md';
        $this->assertFileExists($path);

        $content = \file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }

    private function getReadmeContent(): string
    {
        $path = $this->projectRoot . '/README.md';
        $this->assertFileExists($path);

        $content = \file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }
}
