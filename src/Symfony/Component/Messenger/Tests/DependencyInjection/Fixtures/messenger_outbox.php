<?php

$container->loadFromExtension('messenger', [
    'transports' => [
        'orders' => [
            'dsn' => 'amqp://localhost/%2f/orders',
            'outbox' => 'outbox',
        ],
        'outbox' => 'doctrine://default?queue_name=outbox',
    ],
]);
