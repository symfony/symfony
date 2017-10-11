<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->addArgument("string with\nnew line")
    ->setPublic(true)
;

return $container;
