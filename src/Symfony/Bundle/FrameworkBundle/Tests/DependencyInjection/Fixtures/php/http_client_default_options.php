<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'max_host_connections' => 4,
        'default_options' => null,
        'clients' => [
            'foo' => [
                'default_options' => null,
            ],
        ],
    ],
]);
