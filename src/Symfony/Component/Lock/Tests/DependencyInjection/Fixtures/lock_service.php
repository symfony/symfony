<?php

$container->register('my_service', \Redis::class);

$container->loadFromExtension('lock', ['default' => 'my_service']);
