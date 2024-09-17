<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'semaphore' => [
        'foo' => 'redis://paas.com',
        'qux' => '%env(REDIS_DSN)%',
    ],
]);
