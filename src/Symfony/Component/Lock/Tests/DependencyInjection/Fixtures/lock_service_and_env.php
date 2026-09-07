<?php

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('lock', [
    'foo' => '%env(REDIS_DSN)%',
    'bar' => 'my_service',
]);
