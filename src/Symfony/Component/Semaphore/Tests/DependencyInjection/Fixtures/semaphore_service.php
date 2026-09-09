<?php

$container->register('my_service', \Redis::class);

$container->loadFromExtension('semaphore', ['default' => 'my_service']);
