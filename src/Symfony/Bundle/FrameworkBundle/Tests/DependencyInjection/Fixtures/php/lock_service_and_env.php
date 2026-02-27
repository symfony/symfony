<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'lock' => [
        'foo' => '%env(REDIS_DSN)%',
        'bar' => 'my_service',
    ],
]);
