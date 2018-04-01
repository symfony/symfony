<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();

$bar = new Definition('Bar');
$bar->setConfigurator(array(new Definition('Baz'), 'configureBar'));

$fooFactory = new Definition('FooFactory');
$fooFactory->setFactory(array(new Definition('Foobar'), 'createFooFactory'));

$container
    ->register('foo', 'Foo')
    ->setFactory(array($fooFactory, 'createFoo'))
    ->setConfigurator(array($bar, 'configureFoo'))
    ->setPublic(true)
;

return $container;
