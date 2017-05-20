<?php

$container->setParameter('env(REDIS_URL)', 'redis://paas.com');

$container->loadFromExtension('framework', array(
    'cache' => array(
        'default_redis_provider' => '%env(REDIS_URL)%',
    ),
));
