<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Definition('BarClass', array(new Definition('BazClass'))))
    ->setPublic(true)
;

return $container;
