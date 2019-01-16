<?php

$container->loadFromExtension('security', [
    'providers' => [
        'default' => [
            'memory' => [
                'users' => [
                    'foo' => ['password' => 'foo', 'roles' => 'ROLE_USER'],
                ],
            ],
        ],
    ],

    'firewalls' => [
        'simple_auth' => [
            'provider' => 'default',
            'anonymous' => true,
            'simple_form' => ['authenticator' => 'simple_authenticator'],
        ],
    ],
]);
