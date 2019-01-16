<?php

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container
    ->register('foo1', 'stdClass')
    ->setPublic(true)
;

$container
    ->register('foo2', 'stdClass')
;

$container
    ->register('foo3', 'stdClass')
    ->setShared(false)
;

$container
    ->register('foo4', 'stdClass')
    ->addError('BOOM')
;

$container
    ->register('foo5', 'stdClass')
    ->setPublic(true)
    ->setSynthetic(true)
;

$container
    ->register('bar', 'stdClass')
    ->setProperty('locator', new ServiceLocatorArgument([
        'foo1' => new Reference('foo1'),
        'foo2' => new Reference('foo2'),
        'foo3' => new Reference('foo3'),
        'foo4' => new Reference('foo4', $container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE),
        'foo5' => new Reference('foo5', $container::IGNORE_ON_UNINITIALIZED_REFERENCE),
    ]))
    ->setPublic(true)
;

return $container;
