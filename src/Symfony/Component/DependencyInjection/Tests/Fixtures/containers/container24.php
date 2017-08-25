<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->setAutowired(true)
    ->addAutowiringType('A')
    ->addAutowiringType('B')
;

return $container;
