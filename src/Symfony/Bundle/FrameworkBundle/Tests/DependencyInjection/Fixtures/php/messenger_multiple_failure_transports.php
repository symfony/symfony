<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'messenger' => [
        'transports' => [
            'transport_1' => [
                'dsn' => 'null://',
                'failure_transport' => 'failure_transport_1'
            ],
            'transport_2' => 'null://',
            'transport_3' => [
                'dsn' => 'null://',
                'failure_transport' => 'failure_transport_3'
            ],
            'failure_transport_1' => 'null://',
            'failure_transport_3' => 'null://'
        ],
    ],
]);
