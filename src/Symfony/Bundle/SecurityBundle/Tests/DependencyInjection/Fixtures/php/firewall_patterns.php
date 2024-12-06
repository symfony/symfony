<?php

$container->loadFromExtension('security', [
    'erase_credentials' => false,
    'firewalls' => [
        'no_security' => [
            'pattern' => [
                '^/register$',
                '^/documentation$',
            ],
        ],
    ],
]);
