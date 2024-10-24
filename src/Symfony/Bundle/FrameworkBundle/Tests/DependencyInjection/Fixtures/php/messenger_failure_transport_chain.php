<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'messenger' => [
        'transports' => [
            'transport_1' => [
                'dsn' => 'null://',
                'failure_transport' => 'transport_2',
            ],
            'transport_2' => [
                'dsn' => 'null://',
                'failure_transport' => 'failure_transport_1',
            ],
            'failure_transport_1' => 'null://',
        ],
    ],
]);
