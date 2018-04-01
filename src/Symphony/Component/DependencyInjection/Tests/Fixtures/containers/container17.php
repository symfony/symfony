<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container
    ->register('foo', '%foo.class%')
    ->setPublic(true)
;

return $container;
