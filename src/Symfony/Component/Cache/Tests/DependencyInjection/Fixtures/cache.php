<?php

$container->loadFromExtension('cache', [
    'pools' => [
        'cache.foo' => [
            'adapters' => 'cache.adapter.apcu',
            'default_lifetime' => 30,
        ],
        'cache.bar' => [
            'adapters' => 'cache.adapter.array',
            'tags' => true,
            'public' => true,
        ],
    ],
]);
