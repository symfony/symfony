<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'http_client' => [
        'max_host_connections' => 4,
        'default_options' => null,
        'scoped_clients' => [
            'foo' => [
                'base_uri' => 'http://example.com',
            ],
        ],
    ],
]);
