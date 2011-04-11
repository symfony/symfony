<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => array(
        'enabled'    => true,
        'field_name' => '_csrf',
        'secret'     => 's3cr3t',
    ),
    'esi' => array(
        'enabled' => true,
    ),
    'profiler' => array(
        'only_exceptions' => true,
    ),
    'router' => array(
        'cache_warmer' => true,
        'resource'     => '%kernel.root_dir%/config/routing.xml',
        'type'         => 'xml',
    ),
    'session' => array(
        'auto_start'     => true,
        'class'          => 'Session',
        'default_locale' => 'fr',
        'storage_id'     => 'native',
        'name'           => '_SYMFONY',
        'lifetime'       => 86400,
        'path'           => '/',
        'domain'         => 'example.com',
        'secure'         => true,
        'httponly'       => true,
    ),
    'templating' => array(
        'assets_version'   => '1.0.0',
        'assets_base_urls' => '//default.example.com',
        'cache_warmer'     => true,
        'cache'            => '/path/to/cache',
        'engines'          => array('php', 'twig'),
        'loader'           => array('loader.foo', 'loader.bar'),
        'packages' => array(
            'basic' => array(
                'version' => 'basic1',
                'base_urls' => array(
                    '//basic1.example.com',
                    '//basic2.example.com',
                    '//basic3.example.com',
                    '//basic4.example.com',
                ),
            ),
            'images' => array(
                'base_urls' => array(
                    'ssl' => 'https://asdf.cloudfront.net',
                    'http' => array(
                        'http://images1.example.com',
                        'http://images2.example.com',
                        'http://images3.example.com',
                        'http://images4.example.com',
                    ),
                ),
            ),
        ),
    ),
    'translator' => array(
        'enabled'  => true,
        'fallback' => 'fr',
    ),
    'validation' => array(
        'enabled' => true,
        'cache'   => 'apc',
    ),
));
