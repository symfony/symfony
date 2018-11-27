<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls;

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

// doctrine-like event system + some extra

$container->register('manager', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('connection'));

$container->register('logger', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('connection'))
    ->setProperty('handler', (new Definition('stdClass'))->addArgument(new Reference('manager')))
;
$container->register('connection', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('dispatcher'))
    ->addArgument(new Reference('config'));

$container->register('config', 'stdClass')->setPublic(false)
    ->setProperty('logger', new Reference('logger'));

$container->register('dispatcher', 'stdClass')->setPublic($public)
    ->setLazy($public)
    ->setProperty('subscriber', new Reference('subscriber'));

$container->register('subscriber', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('manager'));

// doctrine-like event system + some extra (bis)

$container->register('manager2', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('connection2'));

$container->register('logger2', 'stdClass')->setPublic(false)
    ->addArgument(new Reference('connection2'))
    ->setProperty('handler2', (new Definition('stdClass'))->addArgument(new Reference('manager2')))
;
$container->register('connection2', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('dispatcher2'))
    ->addArgument(new Reference('config2'));

$container->register('config2', 'stdClass')->setPublic(false)
    ->setProperty('logger2', new Reference('logger2'));

$container->register('dispatcher2', 'stdClass')->setPublic($public)
    ->setLazy($public)
    ->setProperty('subscriber2', new Reference('subscriber2'));

$container->register('subscriber2', 'stdClass')->setPublic(false)
    ->addArgument(new Reference('manager2'));

// private service involved in a loop

$container->register('foo6', 'stdClass')
    ->setPublic(true)
    ->setProperty('bar6', new Reference('bar6'));

$container->register('bar6', 'stdClass')
    ->setPublic(false)
    ->addArgument(new Reference('foo6'));

$container->register('baz6', 'stdClass')
    ->setPublic(true)
    ->setProperty('bar6', new Reference('bar6'));

// provided by Christian Schiffler

$container
    ->register('root', 'stdClass')
    ->setArguments([new Reference('level2'), new Reference('multiuse1')])
    ->setPublic(true);

$container
    ->register('level2', FooForCircularWithAddCalls::class)
    ->addMethodCall('call', [new Reference('level3')]);

$container->register('multiuse1', 'stdClass');

$container
    ->register('level3', 'stdClass')
    ->addArgument(new Reference('level4'));

$container
    ->register('level4', 'stdClass')
    ->setArguments([new Reference('multiuse1'), new Reference('level5')]);

$container
    ->register('level5', 'stdClass')
    ->addArgument(new Reference('level6'));

$container
    ->register('level6', FooForCircularWithAddCalls::class)
    ->addMethodCall('call', [new Reference('level5')]);

return $container;
