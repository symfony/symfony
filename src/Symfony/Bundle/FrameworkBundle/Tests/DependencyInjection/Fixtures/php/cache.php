<?php

$container->loadFromExtension('framework', array(
    'cache' => array(
        'adapters' => array(
            'foo' => array(
                'type' => 'apcu',
                'options' => array(
                    'default_lifetime' => 30,
                ),
            ),
            'bar' => array(
                'type' => 'doctrine',
                'options' => array(
                    'default_lifetime' => 5,
                    'cache_provider_service' => 'app.doctrine_cache_provider',
                ),
            ),
            'baz' => array(
                'type' => 'filesystem',
                'options' => array(
                    'default_lifetime' => 7,
                    'directory' => 'app/cache/psr',
                ),
            ),
        ),
    ),
));
