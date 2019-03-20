<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'max_host_connections' => 4,
        'default_options' => [
            'headers' => ['foo' => 'bar'],
        ],
        'clients' => [
            'foo' => [
                'max_host_connections' => 5,
                'default_options' => [
                    'headers' => ['bar' => 'baz'],
                ],
            ],
        ],
    ],
]);
