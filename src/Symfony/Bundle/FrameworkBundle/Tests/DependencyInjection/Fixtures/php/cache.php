<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'pools' => array(
            'foo' => array(
                'type' => 'apcu',
                'default_lifetime' => 30,
            ),
            'bar' => array(
                'type' => 'doctrine',
                'default_lifetime' => 5,
                'cache_provider_service' => 'app.doctrine_cache_provider',
            ),
            'baz' => array(
                'type' => 'filesystem',
                'default_lifetime' => 7,
                'directory' => 'app/cache/psr',
            ),
            'foobar' => array(
                'type' => 'psr6',
                'default_lifetime' => 10,
                'cache_provider_service' => 'app.cache_pool',
            ),
        ),
    ),
));
