<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->name('.php_cs')
    ->exclude('vendor')
    ->in(__DIR__)
;

return Config::create()
    ->setRules([
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP70Migration:risky' => true,
        '@PHPUnit75Migration:risky' => true,
        'backtick_to_shell_exec' => true,
        'blank_line_before_statement' => [
            'statements' => ['declare', 'return', 'case'],
        ],
        'comment_to_phpdoc' => false,
        'declare_equal_normalize' => ['space' => 'single'],
        'final_static_access' => true,
        'global_namespace_import' => true,
        'linebreak_after_opening_tag' => true,
        'native_function_invocation' => false,
        'no_unset_on_property' => false,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'phpdoc_to_comment' => false,
        'self_static_accessor' => true,
    ])
    ->setFinder($finder)
    ;