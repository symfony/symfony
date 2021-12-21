<?php

$container->loadFromExtension('framework', [
    'cache' => [
        'app' => 'cache.redis_tag_aware.bar',
        'pools' => [
            'cache.redis_tag_aware.foo' => [
                'adapter' => 'cache.adapter.redis_tag_aware',
            ],
            'cache.redis_tag_aware.bar' => [
                'adapter' => 'cache.redis_tag_aware.foo',
            ],
        ],
    ],
]);
