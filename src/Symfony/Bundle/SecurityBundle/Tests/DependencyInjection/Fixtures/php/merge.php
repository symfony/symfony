<?php

$this->load('merge_import.php');

$container->loadFromExtension('security', [
    'enable_authenticator_manager' => true,
    'providers' => [
        'default' => ['id' => 'foo'],
    ],

    'firewalls' => [
        'main' => [
            'form_login' => false,
            'http_basic' => null,
        ],
    ],

    'role_hierarchy' => [
        'FOO' => ['MOO'],
    ],
]);
