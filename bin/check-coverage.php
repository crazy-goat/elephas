#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks coverage against a minimum threshold.
 *
 * Usage: php bin/check-coverage.php <clover-xml-file> <threshold-percent>
 * Example: php bin/check-coverage.php coverage/clover.xml 80
 *
 * Exits with code 0 if coverage >= threshold, 1 otherwise.
 */

if (\PHP_SAPI !== 'cli') {
    echo "This script is for CLI use only.\n";
    exit(255);
}

if ($argc < 3) {
    echo "Usage: php bin/check-coverage.php <clover-xml-file> <threshold-percent>\n";
    exit(1);
}

$cloverFile = $argv[1];
$threshold = (float) $argv[2];

if (!is_file($cloverFile)) {
    echo "Coverage file not found: $cloverFile\n";
    exit(1);
}

$xml = \simplexml_load_file($cloverFile);
if ($xml === false) {
    echo "Failed to parse coverage file: $cloverFile\n";
    exit(1);
}

$metrics = $xml->project->metrics;
$coveredElements = (int) $metrics['coveredelements'];
$totalElements = (int) $metrics['elements'];

if ($totalElements === 0) {
    echo "No code elements found in coverage report.\n";
    exit(1);
}

$coverage = ($coveredElements / $totalElements) * 100;

echo sprintf("Coverage: %.2f%% (%d/%d elements)\n", $coverage, $coveredElements, $totalElements);
echo sprintf("Threshold: %.2f%%\n", $threshold);

if ($coverage >= $threshold) {
    echo "✓ Coverage meets threshold.\n";
    exit(0);
} else {
    echo "✗ Coverage below threshold!\n";
    exit(1);
}
