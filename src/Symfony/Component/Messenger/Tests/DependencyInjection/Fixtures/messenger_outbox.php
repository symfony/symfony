<?php

$container->loadFromExtension('messenger', [
    'failure_transport' => 'failed',
    'transports' => [
        'orders' => [
            'dsn' => 'amqp://localhost/%2f/orders',
            'outbox' => 'outbox',
        ],
        'outbox' => 'doctrine://default?queue_name=outbox',
        'failed' => 'doctrine://default?queue_name=failed',
    ],
]);
