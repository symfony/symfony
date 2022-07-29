<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'messenger' => [
        'transports' => [
            'schedule' => [
                'dsn' => 'schedule://default',
                'options' => [
                    'cache' => 'array',
                ]
            ],
        ],
    ],
]);
