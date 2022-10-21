<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
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
