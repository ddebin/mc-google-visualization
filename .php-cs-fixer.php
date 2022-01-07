<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->exclude('vendor')
    ->in(__DIR__)
;


$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PhpCsFixer' => true,
    '@PhpCsFixer:risky' => true,
    'array_syntax' => ['syntax' => 'short'],
    'php_unit_test_class_requires_covers' => false,
    'backtick_to_shell_exec' => true,
    'blank_line_before_statement' => [
        'statements' => ['declare', 'return', 'case'],
    ],
    'comment_to_phpdoc' => false,
    'declare_equal_normalize' => ['space' => 'single'],
    'global_namespace_import' => true,
    'linebreak_after_opening_tag' => true,
    'native_function_invocation' => false,
    'no_unset_on_property' => false,
    'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    'phpdoc_to_comment' => false,
    'self_static_accessor' => true,
])
    ->setFinder($finder);
