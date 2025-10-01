<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'property_info' => [
        'enabled' => true,
        'with_constructor_extractor' => true,
    ],
]);
