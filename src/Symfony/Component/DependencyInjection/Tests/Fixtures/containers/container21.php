<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container->setParameter('factory.service', 'foo_factory');
$container->setParameter('factory.class', 'FooFactory');
$container->setParameter('factory.method', 'createFoo');

$container
    ->register('foo', 'Foo')
    ->setFactory(array('%factory.class%', '%factory.method%'))
    ->setPublic(false)
;
$container
    ->register('bar', 'Bar')
    ->setFactory(array(new Definition('%factory.class%'), '%factory.method%'))
    ->setPublic(false)
;
$container
    ->register('foobar', 'Foobar')
    ->addMethodCall('setFoo', array(new Reference('foo')))
    ->addMethodCall('setBar', array(new Reference('bar')))
;

return $container;
