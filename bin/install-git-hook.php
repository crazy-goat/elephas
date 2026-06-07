<?php

declare(strict_types=1);

/**
 * Install the project's Git pre-push hook.
 *
 * Usage:
 *   php bin/install-git-hook.php            # interactive (prompts before overwriting)
 *   php bin/install-git-hook.php --force    # overwrite without prompting
 *   php bin/install-git-hook.php --uninstall  # remove the installed hook
 */

$hookContent = <<<'HOOK'
#!/bin/bash
echo "Running pre-push lint checks..."
composer lint
HOOK;

$projectRoot = dirname(__DIR__);
$gitHookDir = $projectRoot . '/.git/hooks';
$prePushPath = $gitHookDir . '/pre-push';

// ----- Options -----
$force = in_array('--force', $argv ?? [], true);
$uninstall = in_array('--uninstall', $argv ?? [], true);

// ----- Uninstall -----
if ($uninstall) {
    if (!file_exists($prePushPath)) {
        echo "No pre-push hook found, nothing to uninstall.\n";
        exit(0);
    }

    $realHook = realpath($prePushPath);
    $projectReal = realpath($projectRoot);

    // Only remove hooks that were installed by this script (check content).
    $currentContent = file_get_contents($prePushPath);
    if ($currentContent === $hookContent) {
        unlink($prePushPath);
        echo "Pre-push hook removed.\n";
        exit(0);
    }

    echo "Pre-push hook was not installed by this project; skipping removal.\n";
    exit(0);
}

// ----- Check .git directory -----
if (!is_dir($gitHookDir)) {
    echo "Info: .git/hooks directory not found – not a Git repository or no hooks directory.\n";
    echo "To install the hook manually later, run: php bin/install-git-hook.php --force\n";
    exit(0);
}

// ----- Check for existing hook -----
if (file_exists($prePushPath)) {
    $currentContent = file_get_contents($prePushPath);

    if ($currentContent === $hookContent) {
        echo "Pre-push hook is already installed and up-to-date.\n";
        exit(0);
    }

    // Existing hook is different – preserve it.
    if (!$force) {
        echo "An existing pre-push hook was found at: $prePushPath\n";
        echo "\n";
        echo "To keep your existing hook and install the project hook as a separate file,\n";
        echo "you can rename it manually.\n";
        echo "\n";
        echo "Run with --force to overwrite (your existing hook will be backed up).\n";
        exit(0);
    }

    // Backup existing hook.
    $backupPath = $prePushPath . '.backup';
    $backupIndex = 1;
    while (file_exists($backupPath)) {
        $backupPath = $prePushPath . '.backup.' . $backupIndex;
        $backupIndex++;
    }

    if (copy($prePushPath, $backupPath)) {
        echo "Existing pre-push hook backed up to: $backupPath\n";
    } else {
        echo "Warning: could not back up existing hook.\n";
    }
}

// ----- Write hook -----
if (file_put_contents($prePushPath, $hookContent) === false) {
    echo "Error: Failed to write pre-push hook\n";
    exit(1);
}

chmod($prePushPath, 0755);
echo "Git pre-push hook installed successfully.\n";
