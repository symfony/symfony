<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'access_token' => [
        'enabled' => true,
        'credentials' => [
            'my_test_provider' => [
                'url' => 'oauth://user123:pass123@example.tld?scope=DoThis',
            ],
        ],
    ],
]);
