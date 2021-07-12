<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'failure_transport' => 'failed',
        'transports' => [
            'example' => 'redis://127.0.0.1:6379/messages',
            'failed' => 'in-memory:///',
        ],
    ],
]);
