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
        ],
    ],

    'role_hierarchy' => [
        'FOO' => ['MOO'],
    ],
]);
