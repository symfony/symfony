<?php

$container->register('my_service', \Redis::class);

$container->loadFromExtension('framework', [
    'lock' => 'my_service',
]);
