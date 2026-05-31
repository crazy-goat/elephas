<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

$rules = [
    '@PER-CS2x0' => true,
    '@PER-CS2x0:risky' => true,
    'array_syntax' => ['syntax' => 'short'],
    'align_multiline_comment' => true,
    'cast_spaces' => true,
    'no_empty_comment' => true,
    'no_unused_imports' => true,
    'phpdoc_scalar' => true,
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_trim' => true,
    'phpdoc_var_annotation_correct_order' => true,
    'no_empty_statement' => true,
    'no_spaces_around_offset' => true,
    'declare_strict_types' => true,
    'strict_comparison' => true,
    'ordered_imports' => true,
    'get_class_to_class_keyword' => true,
    'no_superfluous_phpdoc_tags' => true,
    'trailing_comma_in_multiline' => [
        'after_heredoc' => true,
        'elements' => ['arrays', 'match', 'arguments', 'parameters'],
    ],
    'single_line_empty_body' => false,
];

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules($rules)
    ->setRiskyAllowed(true)
;
