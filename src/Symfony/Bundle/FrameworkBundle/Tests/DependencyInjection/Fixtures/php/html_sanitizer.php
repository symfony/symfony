<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'html_sanitizer' => [
        'sanitizers' => [
            'custom' => [
                'allow_safe_elements' => true,
                'allow_static_elements' => true,
                'allow_elements' => [
                    'iframe' => 'src',
                    'custom-tag' => ['data-attr', 'data-attr-1'],
                    'custom-tag-2' => '*',
                ],
                'block_elements' => ['section'],
                'drop_elements' => ['video'],
                'allow_attributes' => [
                    'src' => ['iframe'],
                    'data-attr' => '*',
                ],
                'drop_attributes' => [
                    'data-attr' => ['custom-tag'],
                    'data-attr-1' => [],
                    'data-attr-2' => '*',
                ],
                'force_attributes' => [
                    'a' => ['rel' => 'noopener noreferrer'],
                    'h1' => ['class' => 'bp4-heading'],
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
            'all.sanitizer' => null,
        ],
    ],
]);
