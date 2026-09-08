<?php

$container->setParameter('env(APP_CACHE_DSN)', 'redis://localhost');

$container->loadFromExtension('cache', [
    'default_provider' => '%env(APP_CACHE_DSN)%',
    'prefix_seed' => 'my-app',
]);
