<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegisterListenersPassTest extends TestCase
{
    /**
     * Tests that event subscribers not implementing EventSubscriberInterface
     * trigger an exception.
     */
    public function testEventSubscriberWithoutInterface()
    {
        $this->expectException('InvalidArgumentException');
        $builder = new ContainerBuilder();
        $builder->register('event_dispatcher');
        $builder->register('my_event_subscriber', 'stdClass')
            ->addTag('kernel.event_subscriber');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);
    }

    public function testValidEventSubscriber()
    {
        $builder = new ContainerBuilder();
        $eventDispatcherDefinition = $builder->register('event_dispatcher');
        $builder->register('my_event_subscriber', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService')
            ->addTag('kernel.event_subscriber');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);

        $expectedCalls = [
            [
                'addListener',
                [
                    'event',
                    [new ServiceClosureArgument(new Reference('my_event_subscriber')), 'onEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $eventDispatcherDefinition->getMethodCalls());
    }

    public function testAliasedEventSubscriber(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('event_dispatcher.event_aliases', [AliasedEvent::class => 'aliased_event']);
        $builder->register('event_dispatcher');
        $builder->register('my_event_subscriber', AliasedSubscriber::class)
            ->addTag('kernel.event_subscriber');

        $eventAliasPass = new AddEventAliasesPass([CustomEvent::class => 'custom_event']);
        $eventAliasPass->process($builder);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);

        $expectedCalls = [
            [
                'addListener',
                [
                    'aliased_event',
                    [new ServiceClosureArgument(new Reference('my_event_subscriber')), 'onAliasedEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'custom_event',
                    [new ServiceClosureArgument(new Reference('my_event_subscriber')), 'onCustomEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $builder->getDefinition('event_dispatcher')->getMethodCalls());
    }

    public function testAbstractEventListener()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The service "foo" tagged "kernel.event_listener" must not be abstract.');
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_listener', []);
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testAbstractEventSubscriber()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The service "foo" tagged "kernel.event_subscriber" must not be abstract.');
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testEventSubscriberResolvableClassName()
    {
        $container = new ContainerBuilder();

        $container->setParameter('subscriber.class', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService');
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    'event',
                    [new ServiceClosureArgument(new Reference('foo')), 'onEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    public function testHotPathEvents()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        (new RegisterListenersPass())->setHotPathEvents(['event'])->process($container);

        $this->assertTrue($container->getDefinition('foo')->hasTag('container.hot_path'));
    }

    public function testEventSubscriberUnresolvableClassName()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('You have requested a non-existent parameter "subscriber.class"');
        $container = new ContainerBuilder();
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testInvokableEventListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)->addTag('kernel.event_listener', ['event' => 'foo.bar']);
        $container->register('bar', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo.bar']);
        $container->register('baz', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event']);
        $container->register('event_dispatcher', \stdClass::class);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    'foo.bar',
                    [new ServiceClosureArgument(new Reference('foo')), 'onFooBar'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'foo.bar',
                    [new ServiceClosureArgument(new Reference('bar')), '__invoke'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'event',
                    [new ServiceClosureArgument(new Reference('baz')), 'onEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    public function testAliasedEventListener(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('event_dispatcher.event_aliases', [AliasedEvent::class => 'aliased_event']);
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => AliasedEvent::class, 'method' => 'onEvent']);
        $container->register('bar', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => CustomEvent::class, 'method' => 'onEvent']);
        $container->register('event_dispatcher');

        $eventAliasPass = new AddEventAliasesPass([CustomEvent::class => 'custom_event']);
        $eventAliasPass->process($container);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    'aliased_event',
                    [new ServiceClosureArgument(new Reference('foo')), 'onEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'custom_event',
                    [new ServiceClosureArgument(new Reference('bar')), 'onEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    public function testOmitEventNameOnTypedListener(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('event_dispatcher.event_aliases', [AliasedEvent::class => 'aliased_event']);
        $container->register('foo', TypedListener::class)->addTag('kernel.event_listener', ['method' => 'onEvent']);
        $container->register('bar', TypedListener::class)->addTag('kernel.event_listener');
        $container->register('event_dispatcher');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    CustomEvent::class,
                    [new ServiceClosureArgument(new Reference('foo')), 'onEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'aliased_event',
                    [new ServiceClosureArgument(new Reference('bar')), '__invoke'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    public function testOmitEventNameOnUntypedListener(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener', ['method' => 'onEvent']);
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testOmitEventNameAndMethodOnUntypedListener(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener');
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     * @requires PHP 7.2
     */
    public function testOmitEventNameAndMethodOnGenericListener(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', GenericListener::class)->addTag('kernel.event_listener');
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testOmitEventNameOnSubscriber(): void
    {
        $container = new ContainerBuilder();
        $container->register('subscriber', IncompleteSubscriber::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('kernel.event_listener')
            ->addTag('kernel.event_listener', ['event' => 'bar', 'method' => 'onBar'])
        ;
        $container->register('event_dispatcher');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    'bar',
                    [new ServiceClosureArgument(new Reference('subscriber')), 'onBar'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'foo',
                    [new ServiceClosureArgument(new Reference('subscriber')), 'onFoo'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }
}

class SubscriberService implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'event' => 'onEvent',
        ];
    }
}

class InvokableListenerService
{
    public function __invoke()
    {
    }

    public function onEvent()
    {
    }
}

final class AliasedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AliasedEvent::class => 'onAliasedEvent',
            CustomEvent::class => 'onCustomEvent',
        ];
    }
}

final class AliasedEvent
{
}

final class CustomEvent
{
}

final class TypedListener
{
    public function __invoke(AliasedEvent $event): void
    {
    }

    public function onEvent(CustomEvent $event): void
    {
    }
}

final class GenericListener
{
    public function __invoke(object $event): void
    {
    }
}

final class IncompleteSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'foo' => 'onFoo',
        ];
    }

    public function onFoo(): void
    {
    }

    public function onBar(): void
    {
    }

    public function __invoke(CustomEvent $event): void
    {
    }
}
