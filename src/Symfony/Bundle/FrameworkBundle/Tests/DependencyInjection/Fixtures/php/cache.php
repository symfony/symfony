<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'adapters' => array(
            'foo' => array(
                'parent' => 'cache.adapter.filesystem',
                'default_lifetime' => 30,
            ),
            'doctrine' => array(
                'provider' => 'app.doctrine_cache_provider',
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
                'provider' => 'app.doctrine_cache_provider',
            ),
            'baz' => array(
                'adapter' => 'cache.adapter.filesystem',
                'default_lifetime' => 7,
            ),
            'foobar' => array(
                'adapter' => 'cache.adapter.psr6',
                'default_lifetime' => 10,
                'provider' => 'app.cache_pool',
            ),
            'def' => array(
                'default_lifetime' => 11,
            ),
        ),
    ),
));
