<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

$public = 'public' === $visibility;
$container = new ContainerBuilder();

// same visibility for deps

$container->register('foo', FooCircular::class)->setPublic(true)
   ->addArgument(new Reference('bar'));

$container->register('bar', BarCircular::class)->setPublic($public)
    ->addMethodCall('addFoobar', array(new Reference('foobar')));

$container->register('foobar', FoobarCircular::class)->setPublic($public)
    ->addArgument(new Reference('foo'));

// mixed visibility for deps

$container->register('foo2', FooCircular::class)->setPublic(true)
   ->addArgument(new Reference('bar2'));

$container->register('bar2', BarCircular::class)->setPublic(!$public)
    ->addMethodCall('addFoobar', array(new Reference('foobar2')));

$container->register('foobar2', FoobarCircular::class)->setPublic($public)
    ->addArgument(new Reference('foo2'));

// simple inline setter with internal reference

$container->register('bar3', BarCircular::class)->setPublic(true)
    ->addMethodCall('addFoobar', array(new Reference('foobar3'), new Reference('foobar3')));

$container->register('foobar3', FoobarCircular::class)->setPublic($public);

// loop with non-shared dep

$container->register('foo4', 'stdClass')->setPublic($public)
    ->setShared(false)
    ->setProperty('foobar', new Reference('foobar4'));

$container->register('foobar4', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('foo4'));

// loop on the constructor of a setter-injected dep with property

$container->register('foo5', 'stdClass')->setPublic(true)
    ->setProperty('bar', new Reference('bar5'));

$container->register('bar5', 'stdClass')->setPublic($public)
    ->addArgument(new Reference('foo5'))
    ->setProperty('foo', new Reference('foo5'));

return $container;
