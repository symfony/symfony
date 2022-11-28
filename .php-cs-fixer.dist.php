<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHPUnit48Migration:risky' => true,
        'php_unit_no_expectation_annotation' => false, // part of `PHPUnitXYMigration:risky` ruleset, to be enabled when PHPUnit 4.x support will be dropped, as we don't want to rewrite exceptions handling twice
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'ordered_imports' => true,
        'protected_to_private' => false,
        // Part of @Symfony:risky in PHP-CS-Fixer 2.13.0. To be removed from the config file once upgrading
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        // Part of future @Symfony ruleset in PHP-CS-Fixer To be removed from the config file once upgrading
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__)
            ->exclude('vendor')
            ->name('*.php')
    )
;
