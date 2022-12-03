<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'router' => [
        'resource' => '%kernel.project_dir%/config/routing.xml',
        'type' => 'xml',
        'utf8' => true,
    ],
    'translator' => [
        'enabled' => true,
        'fallback' => 'fr',
        'paths' => ['%kernel.project_dir%/Fixtures/translations'],
        'cache_dir' => '%kernel.cache_dir%/translations',
        'enabled_locales' => ['fr', 'en'],
    ],
]);
