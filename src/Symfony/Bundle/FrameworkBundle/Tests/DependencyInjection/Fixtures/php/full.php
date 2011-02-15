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
        'assets_version'   => 'SomeVersionScheme',
        'assets_base_urls' => 'http://cdn.example.com',
        'cache_warmer'     => true,
        'engines'          => array('php', 'twig'),
        'loader'           => array('loader.foo', 'loader.bar'),
    ),
    'translator' => array(
        'enabled'  => true,
        'fallback' => 'fr',
    ),
    'validation' => array(
        'enabled' => true,
    ),
));
