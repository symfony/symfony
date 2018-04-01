<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->setAutowired(true)
    ->setPublic(true)
;

return $container;
