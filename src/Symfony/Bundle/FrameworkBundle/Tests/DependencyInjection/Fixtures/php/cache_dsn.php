<?php

$container->loadFromExtension('framework', [
    'cache' => [
        'app' => 'redis://localhost',
        'pools' => [
            'cache.custom_dsn' => [
                'adapter' => 'memcached://localhost',
            ],
            'cache.env_dsn' => [
                'adapter' => '%env(CACHE_DSN)%',
            ],
        ],
    ],
]);
