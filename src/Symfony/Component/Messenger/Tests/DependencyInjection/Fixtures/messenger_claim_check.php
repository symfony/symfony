<?php

$container->loadFromExtension('messenger', [
    'transports' => [
        'async' => [
            'dsn' => 'in-memory:///',
            'claim_check' => [
                'cache_pool' => 'app.claim_check_pool',
                'max_size' => 200000,
            ],
        ],
    ],
]);
