<?php

$container->loadFromExtension('security', [
    'providers' => [
        'default' => [
            'memory' => $memory = [
                'users' => ['foo' => ['password' => 'foo', 'roles' => 'ROLE_USER']],
            ],
        ],
        'with-dash' => [
            'memory' => $memory,
        ],
    ],
    'firewalls' => [
        'main' => [
            'provider' => 'default',
            'form_login' => true,
        ],
        'other' => [
            'provider' => 'with-dash',
            'form_login' => true,
        ],
    ],
]);
