<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container
    ->register('foo', 'FooClass\\Foo')
    ->setDecoratedService('bar', 'bar.woozy')
    ->setPublic(true)
;

return $container;
