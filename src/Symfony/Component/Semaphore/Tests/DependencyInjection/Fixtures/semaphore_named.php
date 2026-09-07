<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('semaphore', [
    'foo' => 'redis://paas.com',
    'qux' => '%env(REDIS_DSN)%',
]);
