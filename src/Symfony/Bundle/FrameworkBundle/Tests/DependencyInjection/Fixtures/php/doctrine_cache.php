<?php

$container->loadFromExtension('framework', [
    'cache' => [
        'pools' => [
            'cache.bar' => [
                'adapter' => 'cache.adapter.doctrine',
                'default_lifetime' => 5,
                'provider' => 'app.doctrine_cache_provider',
            ],
        ],
    ],
]);
