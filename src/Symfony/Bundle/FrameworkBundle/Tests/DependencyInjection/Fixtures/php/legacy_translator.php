<?php

$container->loadFromExtension('framework', [
    'enabled_locales' => ['fr', 'en'],
    'translator' => [
        'enabled' => true,
        'fallback' => 'fr',
        'default_path' => '%kernel.project_dir%/translations',
        'paths' => ['%kernel.project_dir%/Fixtures/translations'],
        'cache_dir' => '%kernel.cache_dir%/translations',
    ],
]);
