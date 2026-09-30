<?php

$container->loadFromExtension('messenger', [
    'transports' => [
        'async' => [
            'dsn' => 'in-memory:///',
            'sign' => true,
            'failure_transport' => 'failed',
            'claim_check' => [
                'cache_pool' => 'app.claim_check_pool',
                'max_size' => 200000,
            ],
        ],
        'failed' => [
            'dsn' => 'in-memory:///',
            'serializer' => 'messenger.transport.symfony_serializer',
            'sign' => true,
        ],
        'plain' => 'in-memory:///',
    ],
]);
