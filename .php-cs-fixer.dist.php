<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->name('*.php')
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->notPath('src/Kernel.php')
    ->notPath('config/')
    ->ignoreDotFiles(true)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => false,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'return_type_declaration' => ['space_before' => 'none'],
        'single_line_throw' => false,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'cast_spaces' => ['space' => 'single'],
        'concat_space' => ['spacing' => 'one'],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'modifier_keywords' => [
            'elements' => ['property', 'method', 'const'],
        ],
        'nullable_type_declaration' => ['syntax' => 'question_mark'],
        'nullable_type_declaration_for_default_null_value' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
