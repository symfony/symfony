<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'csrf_protection' => true,
    'form' => [
        'csrf_protection' => [
            'field_name' => '_csrf',
        ],
    ],
    'http_method_override' => false,
    'esi' => [
        'enabled' => true,
    ],
    'ssi' => [
        'enabled' => true,
    ],
    'profiler' => [
        'only_exceptions' => true,
        'enabled' => false,
    ],
    'router' => [
        'resource' => '%kernel.project_dir%/config/routing.xml',
        'type' => 'xml',
    ],
    'session' => [
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
        'sid_length' => 22,
        'sid_bits_per_character' => 4,
        'save_path' => '/path/to/sessions',
    ],
    'assets' => [
        'version' => 'v1',
    ],
    'translator' => [
        'enabled' => true,
        'fallback' => 'fr',
        'paths' => ['%kernel.project_dir%/Fixtures/translations'],
        'cache_dir' => '%kernel.cache_dir%/translations',
    ],
    'validation' => [
        'enabled' => true,
    ],
    'annotations' => [
        'cache' => 'file',
        'debug' => true,
        'file_cache_dir' => '%kernel.cache_dir%/annotations',
    ],
    'serializer' => [
        'enabled' => true,
        'enable_annotations' => true,
        'name_converter' => 'serializer.name_converter.camel_case_to_snake_case',
        'circular_reference_handler' => 'my.circular.reference.handler',
        'max_depth_handler' => 'my.max.depth.handler',
    ],
    'property_info' => true,
    'ide' => 'file%%link%%format',
    'request' => [
        'formats' => [
            'csv' => [
                'text/csv',
                'text/plain',
            ],
            'pdf' => 'application/pdf',
        ],
    ],
]);
