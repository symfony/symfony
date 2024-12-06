<?php

$container->loadFromExtension('security', [
    'erase_credentials' => false,
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
