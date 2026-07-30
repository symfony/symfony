<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'cache' => [
        'default_provider' => '%env(APP_CACHE_DSN)%',
        'pools' => [
            'my_pool' => ['provider' => 'memcached://localhost'],
        ],
    ],
]);
