<?php

$container->loadFromExtension('messenger', [
    'identity_stamps' => true,
    'failure_transport' => 'failed',
    'transports' => [
        'orders' => [
            'dsn' => 'amqp://localhost/%2f/orders',
            'outbox' => 'outbox',
            'inbox' => true,
        ],
        'invoices' => [
            'dsn' => 'amqp://localhost/%2f/invoices',
            'failure_transport' => 'failed_invoices',
            'inbox' => [
                'connection' => 'billing',
                'table_name' => 'billing_inbox',
                'retention' => 86400,
            ],
        ],
        'outbox' => 'doctrine://default?queue_name=outbox',
        'failed' => 'doctrine://default?queue_name=failed',
        'failed_invoices' => 'doctrine://default?queue_name=failed_invoices',
    ],
]);
