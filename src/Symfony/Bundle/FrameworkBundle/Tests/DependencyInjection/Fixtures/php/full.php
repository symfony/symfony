<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'csrf_protection' => true,
    'form' => array(
        'csrf_protection' => array(
            'field_name' => '_csrf',
        ),
    ),
    'http_method_override' => false,
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
        'cookie_httponly' => false,
        'use_cookies' => true,
        'gc_maxlifetime' => 90000,
        'gc_divisor' => 108,
        'gc_probability' => 1,
        'save_path' => '/path/to/sessions',
    ),
    'templating' => array(
        'cache' => '/path/to/cache',
        'engines' => array('php', 'twig'),
        'loader' => array('loader.foo', 'loader.bar'),
        'form' => array(
            'resources' => array('theme1', 'theme2'),
        ),
        'hinclude_default_template' => 'global_hinclude_template',
    ),
    'assets' => array(
        'version' => 'v1',
    ),
    'translator' => array(
        'enabled' => true,
        'fallback' => 'fr',
        'paths' => array('%kernel.root_dir%/Fixtures/translations'),
    ),
    'validation' => array(
        'enabled' => true,
    ),
    'annotations' => array(
        'cache' => 'file',
        'debug' => true,
        'file_cache_dir' => '%kernel.cache_dir%/annotations',
    ),
    'serializer' => array(
        'enabled' => true,
        'enable_annotations' => true,
        'name_converter' => 'serializer.name_converter.camel_case_to_snake_case',
    ),
    'property_info' => true,
    'ide' => 'file%%link%%format',
    'request' => array(
        'formats' => array(
            'csv' => array(
                'text/csv',
                'text/plain',
            ),
            'pdf' => 'application/pdf',
        ),
    ),
));
