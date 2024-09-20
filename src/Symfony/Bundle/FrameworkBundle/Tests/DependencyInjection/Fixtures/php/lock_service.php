<?php

$container->register('my_service', \Redis::class);
$container->setAlias('factory_public_alias', 'lock.default.factory')
    ->setPublic(true);

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'lock' => 'my_service',
]);
