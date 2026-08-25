<?php

$container->loadFromExtension('framework', [
    'cache' => [
        'pools' => [
            'app.claim_check_pool' => [
                'adapters' => ['cache.adapter.pdo'],
                'default_lifetime' => 604800,
            ],
        ],
    ],
    'messenger' => [
        'transports' => [
            'async' => [
                'dsn' => 'in-memory:///',
                'claim_check' => [
                    'cache_pool' => 'app.claim_check_pool',
                    'max_size' => 200000,
                ],
            ],
        ],
    ],
]);
