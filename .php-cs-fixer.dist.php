<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->exclude(['vendor', 'var'])
    ->name('*.php');

$config = new Config();

$config
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_import_per_statement' => true,
        'no_unreachable_default_argument_value' => false,
        'logical_operators' => false,
        'error_suppression' => false,
        'set_type_to_cast' => false,
        'static_lambda' => false,
        'psr_autoloading' => false,
        'string_line_ending' => false,
        'native_function_invocation' => false,
        'native_constant_invocation' => false,
    ])
    ->setFinder($finder);

return $config;
