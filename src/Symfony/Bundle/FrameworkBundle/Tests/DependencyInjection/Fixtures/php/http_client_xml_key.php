<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'http_client' => [
        'default_options' => [
            'resolve' => [
                'host' => '127.0.0.1',
            ],
        ],
        'scoped_clients' => [
            'foo' => [
                'base_uri' => 'http://example.com',
                'query' => [
                    'key' => 'foo',
                ],
                'resolve' => [
                    'host' => '127.0.0.1',
                ],
            ],
        ],
    ],
]);
