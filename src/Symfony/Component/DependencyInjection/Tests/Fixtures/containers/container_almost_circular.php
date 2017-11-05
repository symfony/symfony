<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container->register('foo', FooCircular::class)->setPublic(true)
   ->addArgument(new Reference('bar'));

$container->register('bar', BarCircular::class)
    ->addMethodCall('addFoobar', array(new Reference('foobar')));

$container->register('foobar', FoobarCircular::class)
    ->addArgument(new Reference('foo'));

return $container;
