<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
;
$container->
    register('bar', 'BarClass')
;
$container->compile();

return $container;
