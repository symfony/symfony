<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'assets' => [
        'version_strategy' => 'assets.custom_version_strategy',
        'base_urls' => 'http://cdn.example.com',
    ],
]);
