<?php

declare(strict_types=1);

namespace CrazyGoat\Elephas\Test\Unit;

use PHPUnit\Framework\TestCase;

class ChangelogTest extends TestCase
{
    private const CHANGELOG_FILE = __DIR__ . '/../../CHANGELOG.md';

    private function getContent(): string
    {
        $this->assertFileExists(self::CHANGELOG_FILE);
        $content = file_get_contents(self::CHANGELOG_FILE);
        $this->assertIsString($content);

        return $content;
    }

    public function testChangelogFileExists(): void
    {
        $this->assertFileExists(self::CHANGELOG_FILE);
    }

    public function testChangelogHasTitle(): void
    {
        $this->assertStringStartsWith("# Changelog\n", $this->getContent());
    }

    public function testReferencesKeepAChangelog(): void
    {
        $content = $this->getContent();
        $this->assertStringContainsString('Keep a Changelog', $content);
        $this->assertStringContainsString('https://keepachangelog.com', $content);
    }

    public function testReferencesSemanticVersioning(): void
    {
        $content = $this->getContent();
        $this->assertStringContainsString('Semantic Versioning', $content);
        $this->assertStringContainsString('https://semver.org', $content);
    }

    public function testHasUnreleasedSection(): void
    {
        $this->assertMatchesRegularExpression('/^## \[Unreleased\]/m', $this->getContent());
    }

    public function testHasVersionSections(): void
    {
        $matches = preg_match_all('/^## \[\d+\.\d+\.\d+\]/m', $this->getContent());
        $this->assertIsInt($matches);
        $this->assertGreaterThanOrEqual(1, $matches, 'At least one released version section required');
    }

    public function testVersionsAreInDescendingOrder(): void
    {
        preg_match_all('/^## \[(\d+)\.(\d+)\.(\d+)\]/m', $this->getContent(), $matches);
        $this->assertGreaterThan(1, count($matches[0]), 'Need at least 2 versions to test ordering');

        $versions = [];
        foreach ($matches[1] as $i => $major) {
            $versions[] = sprintf('%d.%d.%d', $major, $matches[2][$i], $matches[3][$i]);
        }

        $sorted = $versions;
        usort($sorted, version_compare(...));
        $sorted = array_reverse($sorted);

        $this->assertSame($sorted, $versions, 'Versions must be in descending order');
    }

    public function testUnreleasedComesBeforeReleasedVersions(): void
    {
        $content = $this->getContent();
        $unreleasedPos = strpos($content, '## [Unreleased]');
        $firstVersionPos = strpos($content, '## [0.');

        $this->assertNotFalse($unreleasedPos);
        $this->assertNotFalse($firstVersionPos);
        $this->assertLessThan($firstVersionPos, $unreleasedPos);
    }

    public function testHasKnownCategoryHeaders(): void
    {
        $allowed = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'];
        preg_match_all('/^### (.+)$/m', $this->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'At least one category section required');

        foreach ($matches[1] as $category) {
            $this->assertContains(
                $category,
                $allowed,
                sprintf('Unknown category "%s" – allowed: %s', $category, implode(', ', $allowed)),
            );
        }
    }

    public function testEntriesAreBullets(): void
    {
        $content = $this->getContent();
        preg_match_all(
            '/^### (?:Added|Changed|Deprecated|Removed|Fixed|Security)\n((?:(?!^#{2,3} ).+\n?)+)/m',
            $content,
            $matches,
        );

        $this->assertNotEmpty($matches[1], 'No category sections with entries found');

        foreach ($matches[1] as $entriesBlock) {
            $lines = array_values(array_filter(explode("\n", $entriesBlock), static fn(string $l): bool => $l !== ''));
            $this->assertNotEmpty($lines, 'Empty category section found');
            foreach ($lines as $line) {
                $this->assertStringStartsWith('- ', $line, sprintf('Entry must start with "- ": "%s"', $line));
            }
        }
    }

    public function testEntriesReferenceIssues(): void
    {
        $content = $this->getContent();
        preg_match_all('/^- (.+?)$/m', $content, $matches);

        $this->assertNotEmpty($matches[1], 'No bullet entries found');

        foreach ($matches[1] as $entry) {
            $this->assertMatchesRegularExpression(
                '/\(#\d+(?:, #\d+)*\)\s*$/',
                $entry,
                sprintf('Entry must end with issue reference(s) like "(#N)" or "(#N, #M)": "%s"', $entry),
            );
        }
    }

    public function testContainsV020Section(): void
    {
        $this->assertStringContainsString('## [0.2.0]', $this->getContent());
    }

    public function testContainsV010Section(): void
    {
        $this->assertStringContainsString('## [0.1.0]', $this->getContent());
    }

    public function testNoTrailingWhitespace(): void
    {
        $content = $this->getContent();
        $this->assertDoesNotMatchRegularExpression('/[ \t]+$/m', $content);
    }

    public function testEndsWithNewline(): void
    {
        $this->assertStringEndsWith("\n", $this->getContent());
    }
}
