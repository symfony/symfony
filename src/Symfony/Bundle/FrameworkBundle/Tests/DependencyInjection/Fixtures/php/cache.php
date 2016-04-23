<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'adapters' => array(
            'foo' => array(
                'parent' => 'cache.adapter.filesystem',
                'default_lifetime' => 30,
            ),
            'app_redis' => array(
                'parent' => 'cache.adapter.redis',
                'provider' => 'app.redis_connection',
                'default_lifetime' => 30,
            ),
        ),
        'pools' => array(
            'foo' => array(
                'adapter' => 'cache.adapter.apcu',
                'default_lifetime' => 30,
            ),
            'bar' => array(
                'adapter' => 'cache.adapter.doctrine',
                'default_lifetime' => 5,
            ),
            'baz' => array(
                'adapter' => 'cache.adapter.filesystem',
                'default_lifetime' => 7,
            ),
            'foobar' => array(
                'adapter' => 'cache.adapter.psr6',
                'default_lifetime' => 10,
            ),
            'def' => array(
                'default_lifetime' => 11,
            ),
        ),
    ),
));
