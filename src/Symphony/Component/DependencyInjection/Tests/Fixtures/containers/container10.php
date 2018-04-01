<?php

require_once __DIR__.'/../includes/classes.php';

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
    ->setPublic(true)
;

return $container;
