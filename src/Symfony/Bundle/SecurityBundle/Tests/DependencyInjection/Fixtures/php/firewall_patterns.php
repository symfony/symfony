<?php

$container->loadFromExtension('security', [
    'firewalls' => [
        'no_security' => [
            'pattern' => [
                '^/register$',
                '^/documentation$',
            ],
        ],
    ],
]);
