<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'http_client' => [
        'scoped_clients' => [
            'foo' => [
                'scope' => '.*',
            ],
        ],
    ],
]);
