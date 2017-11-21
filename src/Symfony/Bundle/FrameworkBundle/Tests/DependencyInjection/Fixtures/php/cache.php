<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'pools' => array(
            'cache.foo' => array(
                'adapter' => 'cache.adapter.apcu',
                'default_lifetime' => 30,
            ),
            'cache.bar' => array(
                'adapter' => 'cache.adapter.doctrine',
                'default_lifetime' => 5,
                'provider' => 'app.doctrine_cache_provider',
            ),
            'cache.baz' => array(
                'adapter' => 'cache.adapter.filesystem',
                'default_lifetime' => 7,
            ),
            'cache.foobar' => array(
                'adapter' => 'cache.adapter.psr6',
                'default_lifetime' => 10,
                'provider' => 'app.cache_pool',
            ),
            'cache.def' => array(
                'default_lifetime' => 11,
            ),
        ),
    ),
));
