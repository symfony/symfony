<?php

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container
    ->register('foo1', 'stdClass')
    ->setPublic(true)
;

$container
    ->register('foo2', 'stdClass')
    ->setPublic(false)
;

$container
    ->register('foo3', 'stdClass')
    ->setPublic(false)
;

$container
    ->register('baz', 'stdClass')
    ->setProperty('foo3', new Reference('foo3'))
    ->setPublic(true)
;

$container
    ->register('bar', 'stdClass')
    ->setProperty('foo1', new Reference('foo1', $container::IGNORE_ON_UNINITIALIZED_REFERENCE))
    ->setProperty('foo2', new Reference('foo2', $container::IGNORE_ON_UNINITIALIZED_REFERENCE))
    ->setProperty('foo3', new Reference('foo3', $container::IGNORE_ON_UNINITIALIZED_REFERENCE))
    ->setProperty('closures', array(
        new ServiceClosureArgument(new Reference('foo1', $container::IGNORE_ON_UNINITIALIZED_REFERENCE)),
        new ServiceClosureArgument(new Reference('foo2', $container::IGNORE_ON_UNINITIALIZED_REFERENCE)),
        new ServiceClosureArgument(new Reference('foo3', $container::IGNORE_ON_UNINITIALIZED_REFERENCE)),
    ))
    ->setProperty('iter', new IteratorArgument(array(
        'foo1' => new Reference('foo1', $container::IGNORE_ON_UNINITIALIZED_REFERENCE),
        'foo2' => new Reference('foo2', $container::IGNORE_ON_UNINITIALIZED_REFERENCE),
        'foo3' => new Reference('foo3', $container::IGNORE_ON_UNINITIALIZED_REFERENCE),
    )))
    ->setPublic(true)
;

return $container;
