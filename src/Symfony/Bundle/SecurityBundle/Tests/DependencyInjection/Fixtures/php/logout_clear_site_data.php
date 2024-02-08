<?php

$container->loadFromExtension('security', [
    'providers' => [
        'default' => ['id' => 'foo'],
    ],

    'firewalls' => [
        'main' => [
            'provider' => 'default',
            'form_login' => true,
            'logout' => [
                'clear-site-data' => [
                    'cookies',
                    'executionContexts',
                ],
            ],
        ],
    ],
]);
