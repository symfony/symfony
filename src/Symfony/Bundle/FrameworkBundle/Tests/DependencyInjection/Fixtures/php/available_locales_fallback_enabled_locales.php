<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'translator' => [
        'enabled' => true,
        'fallback' => 'fr',
        'paths' => ['%kernel.project_dir%/Fixtures/translations'],
        'cache_dir' => '%kernel.cache_dir%/translations',
        'enabled_locales' => ['mi', 'fr'],
    ],
]);
