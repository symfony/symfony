<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'default_locale' => 'en',
    'enabled_locales' => ['%env(ROUTER_ENABLED_LOCALE)%', 'fr'],
    'router' => [
        'resource' => '%kernel.project_dir%/config/routing.xml',
    ],
]);
