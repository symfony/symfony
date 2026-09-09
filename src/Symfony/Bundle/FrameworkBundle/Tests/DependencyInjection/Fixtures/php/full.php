<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'default_locale' => 'fr',
    'enabled_locales' => ['fr', 'en'],
    'csrf_protection' => true,
    'form' => [
        'csrf_protection' => [
            'field_name' => '_csrf',
        ],
    ],
    'trust_x_sendfile_type_header' => true,
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
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => 'session.handler.native_file',
        'name' => '_SYMFONY',
        'cookie_lifetime' => 86400,
        'cookie_path' => '/',
        'cookie_domain' => 'example.com',
        'cookie_secure' => true,
        'cookie_samesite' => 'lax',
        'cookie_httponly' => false,
        'use_cookies' => true,
        'gc_maxlifetime' => 90000,
        'gc_divisor' => 108,
        'gc_probability' => 1,
        'save_path' => '/path/to/sessions',
    ],
    'translator' => [
        'enabled' => true,
        'fallback' => 'fr',
        'paths' => ['%kernel.project_dir%/Fixtures/translations'],
        'cache_dir' => '%kernel.cache_dir%/translations',
    ],
    'property_info' => true,
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
