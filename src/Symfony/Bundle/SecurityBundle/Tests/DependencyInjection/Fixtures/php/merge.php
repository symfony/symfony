<?php

$this->load('merge_import.php', $container);

$container->loadFromExtension('security', [
    'providers' => [
        'default' => ['id' => 'foo'],
    ],

    'firewalls' => [
        'main' => [
            'form_login' => false,
            'http_basic' => null,
            'logout_on_user_change' => true,
        ],
    ],

    'role_hierarchy' => [
        'FOO' => ['MOO'],
    ],
]);
