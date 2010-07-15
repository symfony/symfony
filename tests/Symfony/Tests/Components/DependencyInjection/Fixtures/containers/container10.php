<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
;

return $container;
