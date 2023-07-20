<?php

$container->loadFromExtension('framework', [
    'annotations' => [
        'cache' => 'file',
        'debug' => true,
        'file_cache_dir' => '%kernel.cache_dir%/annotations',
    ],
    'http_method_override' => false,
]);
