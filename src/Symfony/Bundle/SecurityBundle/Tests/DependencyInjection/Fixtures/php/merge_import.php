<?php

$container->loadFromExtension('security', [
    'erase_credentials' => false,
    'firewalls' => [
        'main' => [
            'form_login' => [
                'login_path' => '/login',
            ],
        ],
    ],
    'role_hierarchy' => [
        'FOO' => 'BAR',
        'ADMIN' => 'USER',
    ],
]);
