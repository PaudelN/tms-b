<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/routes',
        __DIR__ . '/database',
        __DIR__ . '/tests',
        __DIR__ . '/config',
    ]);

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'], // [] instead of array()
        'single_quote' => true,
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
