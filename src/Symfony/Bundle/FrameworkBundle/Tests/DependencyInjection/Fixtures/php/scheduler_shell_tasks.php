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
            'app.foo' => [
                'scheduler' => 'foo',
                'type' => 'shell',
                'command' => 'echo Symfony!',
                'expression' => '* * * * *',
            ],
        ],
    ],
]);
