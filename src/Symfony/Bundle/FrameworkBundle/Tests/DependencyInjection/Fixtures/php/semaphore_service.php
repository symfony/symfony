<?php

$container->register('my_service', \Redis::class);

$container->loadFromExtension('framework', [
    'semaphore' => 'my_service',
]);
