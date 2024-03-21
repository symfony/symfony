<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'translator' => [
        'include_bundles_translations_in_commands' => [
            'excluded_bundles' => ['CustomPathBundle']
        ],
    ],
]);
