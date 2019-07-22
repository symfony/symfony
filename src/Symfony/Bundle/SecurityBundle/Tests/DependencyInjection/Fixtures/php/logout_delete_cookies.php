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
                'delete_cookies' => [
                    'cookie1-name' => true,
                    'cookie2_name' => true,
                    'cookie3-long_name' => ['path' => '/'],
                ],
            ],
        ],
    ],
]);
