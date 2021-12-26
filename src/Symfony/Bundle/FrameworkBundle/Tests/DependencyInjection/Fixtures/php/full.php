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
    'http_method_override' => false,
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
    'router' => [
        'resource' => '%kernel.project_dir%/config/routing.xml',
        'type' => 'xml',
        'utf8' => true,
    ],
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
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
        'default_context' => ['enable_max_depth' => true],
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
    'html_sanitizer' => [
        'default' => 'my.sanitizer',
        'sanitizers' => [
            'my.sanitizer' => [
                'allow_safe_elements' => true,
                'allow_all_static_elements' => true,
                'allow_elements' => [
                    'custom-tag-1' => ['data-attr-1'],
                    'custom-tag-2' => [],
                    'custom-tag-3' => '*',
                ],
                'block_elements' => [
                    'custom-tag-4',
                ],
                'drop_elements' => [
                    'custom-tag-5',
                ],
                'allow_attributes' => [
                    'data-attr-2' => ['custom-tag-6'],
                    'data-attr-3' => [],
                    'data-attr-4' => '*',
                ],
                'drop_attributes' => [
                    'data-attr-5' => ['custom-tag-6'],
                    'data-attr-6' => [],
                    'data-attr-7' => '*',
                ],
                'force_attributes' => [
                    'custom-tag-7' => [
                        'data-attr-8' => 'value',
                    ],
                ],
                'force_https_urls' => true,
                'allowed_link_schemes' => ['http', 'https', 'mailto'],
                'allowed_link_hosts' => ['symfony.com'],
                'allow_relative_links' => true,
                'allowed_media_schemes' => ['http', 'https', 'data'],
                'allowed_media_hosts' => ['symfony.com'],
                'allow_relative_medias' => true,
                'with_attribute_sanitizers' => [
                    'App\\Sanitizer\\CustomAttributeSanitizer',
                ],
                'without_attribute_sanitizers' => [
                    'App\\Sanitizer\\OtherCustomAttributeSanitizer',
                ],
            ],
        ],
    ],
]);
