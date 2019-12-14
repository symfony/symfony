<?php

$container->loadFromExtension('framework', [
    'scheduler' => [
        'timezone' => 'Europe/Paris',
        'transports' => [],
        'output_path' => '%kernel.project_dir%/var/scheduler',
        'path' => '/_tasks',
        'schedulers' => [],
    ],
]);
