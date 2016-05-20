<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();

$bar = new Definition('BarClass');
$bar->setConfigurator(new Definition('BazClass'));

$container
    ->register('foo', 'FooClass')
    ->setConfigurator($bar)
;

return $container;
