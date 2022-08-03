<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'cache' => [
        'pools' => [
            'cache.foo' => [
                'adapter' => 'cache.adapter.apcu',
                'default_lifetime' => 30,
            ],
            'cache.baz' => [
                'adapter' => 'cache.adapter.filesystem',
                'default_lifetime' => 7,
            ],
            'cache.foobar' => [
                'adapter' => 'cache.adapter.psr6',
                'default_lifetime' => 10,
                'provider' => 'app.cache_pool',
            ],
            'cache.def' => [
                'default_lifetime' => 'PT11S',
            ],
            'cache.expr' => [
                'default_lifetime' => '13 seconds',
            ],
            'cache.chain' => [
                'default_lifetime' => 12,
                'adapter' => [
                    'cache.adapter.array',
                    'cache.adapter.filesystem',
                    'redis://foo' => 'cache.adapter.redis',
                ],
            ],
            'cache.ccc' => [
                'adapter' => 'cache.adapter.array',
                'default_lifetime' => 410,
                'tags' => true,
            ],
            'cache.redis_tag_aware.foo' => [
                'adapter' => 'cache.adapter.redis_tag_aware',
            ],
            'cache.redis_tag_aware.foo2' => [
                'tags' => true,
                'adapter' => 'cache.adapter.redis_tag_aware',
            ],
            'cache.redis_tag_aware.bar' => [
                'adapter' => 'cache.redis_tag_aware.foo',
            ],
            'cache.redis_tag_aware.bar2' => [
                'tags' => true,
                'adapter' => 'cache.redis_tag_aware.foo',
            ],
            'cache.redis_tag_aware.baz' => [
                'adapter' => 'cache.redis_tag_aware.foo2',
            ],
            'cache.redis_tag_aware.baz2' => [
                'tags' => true,
                'adapter' => 'cache.redis_tag_aware.foo2',
            ],
        ],
    ],
]);
