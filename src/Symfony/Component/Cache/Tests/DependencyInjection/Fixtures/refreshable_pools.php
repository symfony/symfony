<?php

$container->loadFromExtension('cache', [
    'pools' => [
        'cache.opted_in' => [
            'adapters' => ['cache.adapter.array'],
            'refreshable' => true,
        ],
        'cache.silent' => [
            'adapters' => ['cache.adapter.array'],
        ],
    ],
]);
