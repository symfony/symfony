<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'default_options' => [
            'headers' => ['foo' => 'bar'],
        ],
        'clients' => [
            'foo' => [
                'default_options' => [
                    'headers' => ['bar' => 'baz'],
                ],
            ],
        ],
    ],
]);
