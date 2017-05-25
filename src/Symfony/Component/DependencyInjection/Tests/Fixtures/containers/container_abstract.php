<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->setAbstract(true)
;

return $container;
