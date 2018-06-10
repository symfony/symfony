<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container
    ->register('foo', 'FooClass\\Foo')
    ->setDecoratedService('bar')
    ->setPublic(true)
;

return $container;
