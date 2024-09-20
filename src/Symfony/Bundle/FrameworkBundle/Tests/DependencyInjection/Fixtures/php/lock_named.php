<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'lock' => [
        'foo' => 'semaphore',
        'bar' => 'flock',
        'baz' => ['semaphore', 'flock'],
        'qux' => '%env(REDIS_DSN)%',
    ],
]);
