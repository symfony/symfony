<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'http_client' => [
        'scoped_clients' => [
            'foo' => [
                'scope' => '.*',
            ],
        ],
    ],
]);
