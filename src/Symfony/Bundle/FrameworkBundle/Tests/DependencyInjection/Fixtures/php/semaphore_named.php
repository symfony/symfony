<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'semaphore' => [
        'foo' => 'redis://paas.com',
        'qux' => '%env(REDIS_DSN)%',
    ],
]);
