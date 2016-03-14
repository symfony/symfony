<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'pools' => array(
            'foo' => array(
                'adapter_service' => 'cache.adapter.apcu',
                'default_lifetime' => 30,
            ),
            'bar' => array(
                'adapter_service' => 'cache.adapter.doctrine',
                'default_lifetime' => 5,
                'provider_service' => 'app.doctrine_cache_provider',
            ),
            'baz' => array(
                'adapter_service' => 'cache.adapter.filesystem',
                'default_lifetime' => 7,
                'directory' => 'app/cache/psr',
            ),
            'foobar' => array(
                'adapter_service' => 'cache.adapter.psr6',
                'default_lifetime' => 10,
                'provider_service' => 'app.cache_pool',
            ),
            'def' => array(
                'default_lifetime' => 11,
            ),
        ),
    ),
));
