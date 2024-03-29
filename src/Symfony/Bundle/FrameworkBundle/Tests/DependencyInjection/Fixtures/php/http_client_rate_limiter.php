<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'rate_limiter' => [
        'foo_limiter' => [
            'lock_factory' => null,
            'policy' => 'token_bucket',
            'limit' => 10,
            'rate' => ['interval' => '5 seconds', 'amount' => 10],
        ],
    ],
    'http_client' => [
        'default_options' => [
            'rate_limiter' => 'default_limiter',
        ],
        'scoped_clients' => [
            'foo' => [
                'base_uri' => 'http://example.com',
                'rate_limiter' => 'foo_limiter',
            ],
        ],
    ],
]);
