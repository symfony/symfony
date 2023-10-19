<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'property_info' => ['enabled' => true],
    'validation' => [
        'email_validation_mode' => 'html5',
        'auto_mapping' => [
            'App\\' => ['foo', 'bar'],
            'Symfony\\' => ['a', 'b'],
            'Foo\\',
        ],
    ],
]);
