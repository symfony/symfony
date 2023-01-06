<?php

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls;

$public = 'public' === $visibility;
$container = new ContainerBuilder();

// factory with lazy injection

$container->register('doctrine.config', 'stdClass')->setPublic(false)
    ->setProperty('resolver', new Reference('doctrine.entity_listener_resolver'))
    ->setProperty('flag', 'ok');

$container->register('doctrine.entity_manager', 'stdClass')->setPublic(true)
    ->setFactory([FactoryChecker::class, 'create'])
    ->addArgument(new Reference('doctrine.config'));
$container->register('doctrine.entity_listener_resolver', 'stdClass')->setPublic($public)
    ->addArgument(new IteratorArgument([new Reference('doctrine.listener')]));
$container->register('doctrine.listener', 'stdClass')->setPublic($public)
    ->addArgument(new Reference('doctrine.entity_manager'));

// multiple path detection

$container->register('pA', 'stdClass')->setPublic(true)
    ->addArgument(new Reference('pB'))
    ->addArgument(new Reference('pC'));

$container->register('pB', 'stdClass')->setPublic($public)
    ->setProperty('d', new Reference('pD'));
$container->register('pC', 'stdClass')->setPublic($public)
    ->setLazy(true)
    ->setProperty('d', new Reference('pD'));

$container->register('pD', 'stdClass')->setPublic($public)
    ->addArgument(new Reference('pA'));

// monolog-like + handler that require monolog

$container->register('monolog.logger', 'stdClass')->setPublic(true)
    ->setProperty('handler', new Reference('mailer.transport'));

$container->register('mailer.transport', 'stdClass')->setPublic($public)
    ->setFactory([new Reference('mailer.transport_factory'), 'create']);

$container->register('mailer.transport_factory', FactoryCircular::class)->setPublic($public)
    ->addArgument(new TaggedIteratorArgument('mailer.transport'));

$container->register('mailer.transport_factory.amazon', 'stdClass')->setPublic($public)
    ->addArgument(new Reference('monolog.logger_2'))
    ->addTag('mailer.transport');

$container->register('monolog.logger_2', 'stdClass')->setPublic($public)
    ->setProperty('handler', new Reference('mailer.transport'));

// monolog-like + handler that require monolog with inlined factory

$container->register('monolog_inline.logger', 'stdClass')->setPublic(true)
    ->setProperty('handler', new Reference('mailer_inline.mailer'));

$container->register('mailer_inline.mailer', 'stdClass')->setPublic(false)
    ->addArgument(
        (new Definition('stdClass'))
            ->setFactory([new Reference('mailer_inline.transport_factory'), 'create'])
    );

$container->register('mailer_inline.transport_factory', FactoryCircular::class)->setPublic($public)
    ->addArgument(new TaggedIteratorArgument('mailer_inline.transport'));

$container->register('mailer_inline.transport_factory.amazon', 'stdClass')->setPublic($public)
    ->addArgument(new Reference('monolog_inline.logger_2'))
    ->addTag('mailer.transport');

$container->register('monolog_inline.logger_2', 'stdClass')->setPublic($public)
    ->setProperty('handler', new Reference('mailer_inline.mailer'));

// same visibility for deps

$container->register('foo', FooCircular::class)->setPublic(true)
   ->addArgument(new Reference('bar'));

$container->register('bar', BarCircular::class)->setPublic($public)
    ->addMethodCall('addFoobar', [new Reference('foobar')]);

$container->register('foobar', FoobarCircular::class)->setPublic($public)
    ->addArgument(new Reference('foo'));

// mixed visibility for deps

$container->register('foo2', FooCircular::class)->setPublic(true)
   ->addArgument(new Reference('bar2'));

$container->register('bar2', BarCircular::class)->setPublic(!$public)
    ->addMethodCall('addFoobar', [new Reference('foobar2')]);

$container->register('foobar2', FoobarCircular::class)->setPublic($public)
    ->addArgument(new Reference('foo2'));

// simple inline setter with internal reference

$container->register('bar3', BarCircular::class)->setPublic(true)
    ->addMethodCall('addFoobar', [new Reference('foobar3'), new Reference('foobar3')]);

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

// doctrine-like event system with listener

$container->register('manager3', 'stdClass')
    ->setLazy(true)
    ->setPublic(true)
    ->addArgument(new Reference('connection3'));

$container->register('connection3', 'stdClass')
    ->setPublic($public)
    ->setProperty('listener', [new Reference('listener3')]);

$container->register('listener3', 'stdClass')
    ->setPublic(true)
    ->setProperty('manager', new Reference('manager3'));

// doctrine-like event system with small differences

$container->register('manager4', 'stdClass')
    ->setLazy(true)
    ->addArgument(new Reference('connection4'));

$container->register('connection4', 'stdClass')
    ->setPublic($public)
    ->setProperty('listener', [new Reference('listener4')]);

$container->register('listener4', 'stdClass')
    ->setPublic(true)
    ->addArgument(new Reference('manager4'));

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
