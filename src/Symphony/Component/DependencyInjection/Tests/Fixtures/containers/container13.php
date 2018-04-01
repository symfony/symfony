<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
    ->setPublic(true)
;
$container->
    register('bar', 'BarClass')
    ->setPublic(true)
;
$container->compile();

return $container;
