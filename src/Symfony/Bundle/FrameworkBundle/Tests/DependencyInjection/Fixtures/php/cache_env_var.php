<?php

$container->setParameter('env(REDIS_URL)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'cache' => [
        'default_redis_provider' => '%env(REDIS_URL)%',
    ],
]);
