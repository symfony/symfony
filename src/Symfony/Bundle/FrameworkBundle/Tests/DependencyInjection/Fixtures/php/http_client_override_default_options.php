<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'max_host_connections' => 4,
        'default_options' => [
            'headers' => ['foo' => 'bar'],
        ],
        'scoped_clients' => [
            'foo' => [
                'base_uri' => 'http://example.com',
                'headers' => ['bar' => 'baz'],
            ],
        ],
    ],
]);
