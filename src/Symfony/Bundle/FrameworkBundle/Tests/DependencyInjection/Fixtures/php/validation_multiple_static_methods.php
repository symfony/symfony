<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'static_method' => ['loadFoo', 'loadBar'],
    ],
]);
