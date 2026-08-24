<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'cache' => [
        'pools' => [
            'my_mongodb_pool' => [
                'adapter' => 'cache.adapter.mongodb',
            ],
        ],
    ],
]);
