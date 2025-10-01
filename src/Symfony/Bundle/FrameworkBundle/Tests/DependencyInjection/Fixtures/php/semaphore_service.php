<?php

$container->register('my_service', \Redis::class);

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'semaphore' => 'my_service',
]);
