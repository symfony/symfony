<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->addArgument("string with\nnew line")
    ->setPublic(true)
;

$container
    ->register('foo2', 'Foo')
    ->addArgument("string with\nnl")
    ->setPublic(true)
;

return $container;
