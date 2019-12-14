<?php

$container->loadFromExtension('framework', [
    'scheduler' => [
        'timezone' => 'Europe/Paris',
        'transports' => [
            'local' => [
                'dsn' => 'local://',
            ],
        ],
        'output_path' => '%kernel.project_dir%/var/scheduler',
        'path' => '/_tasks',
        'schedulers' => [
            'foo' => [
                'transport' => 'local',
            ],
        ],
        'tasks' => [
            'app_foo' => [
                'scheduler' => 'foo',
                'type' => 'command',
                'command' => 'cache:clear',
                'expression' => '* * * * *',
                'options' => [
                    '--env' => 'dev',
                ],
            ],
        ],
    ],
]);
