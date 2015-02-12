<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'form' => null,
    'http_method_override' => false,
    'trusted_proxies' => array('127.0.0.1', '10.0.0.1'),
    'csrf_protection' => array(
        'enabled' => true,
        'field_name' => '_csrf',
    ),
    'esi' => array(
        'enabled' => true,
    ),
    'profiler' => array(
        'only_exceptions' => true,
        'enabled' => false,
    ),
    'router' => array(
        'resource' => '%kernel.root_dir%/config/routing.xml',
        'type' => 'xml',
    ),
    'session' => array(
        'storage_id' => 'session.storage.native',
        'handler_id' => 'session.handler.native_file',
        'name' => '_SYMFONY',
        'cookie_lifetime' => 86400,
        'cookie_path' => '/',
        'cookie_domain' => 'example.com',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'use_cookies' => true,
        'gc_maxlifetime' => 90000,
        'gc_divisor' => 108,
        'gc_probability' => 1,
        'save_path' => '/path/to/sessions',
    ),
    'templating' => array(
        'assets_version' => 'SomeVersionScheme',
        'assets_base_urls' => 'http://cdn.example.com',
        'cache' => '/path/to/cache',
        'engines' => array('php', 'twig'),
        'loader' => array('loader.foo', 'loader.bar'),
        'packages' => array(
            'images' => array(
                'version' => '1.0.0',
                'base_urls' => array('http://images1.example.com', 'http://images2.example.com'),
            ),
            'foo' => array(
                'version' => '1.0.0',
            ),
            'bar' => array(
                'base_urls' => array('http://bar1.example.com', 'http://bar2.example.com'),
            ),
        ),
        'form' => array(
            'resources' => array('theme1', 'theme2'),
        ),
        'hinclude_default_template' => 'global_hinclude_template',
    ),
    'translator' => array(
        'enabled' => true,
        'fallback' => 'fr',
    ),
    'validation' => array(
        'enabled' => true,
        'cache' => 'apc',
    ),
    'annotations' => array(
        'cache' => 'file',
        'debug' => true,
        'file_cache_dir' => '%kernel.cache_dir%/annotations',
    ),
    'ide' => 'file%%link%%format',
));
