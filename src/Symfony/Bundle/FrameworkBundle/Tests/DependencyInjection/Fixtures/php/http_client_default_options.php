<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'max_host_connections' => 4,
        'default_options' => null,
        'scopes' => [
            'foo' => [
                'base_uri' => 'http://example.com'
            ],
        ],
    ],
]);
