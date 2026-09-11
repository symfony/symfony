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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Tests\Fixtures\CustomEvent;
use Symfony\Component\EventDispatcher\Tests\Fixtures\DummyEvent;
use Symfony\Component\EventDispatcher\Tests\Fixtures\TaggedInvokableListener;
use Symfony\Component\EventDispatcher\Tests\Fixtures\TaggedMultiListener;
use Symfony\Component\EventDispatcher\Tests\Fixtures\TaggedUnionTypeListener;

class RegisterListenersPassTest extends TestCase
{
    /**
     * Tests that event subscribers not implementing EventSubscriberInterface
     * trigger an exception.
     */
    public function testEventSubscriberWithoutInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
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

    public function testAliasedEventSubscriber()
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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "foo" tagged "kernel.event_listener" must not be abstract.');
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_listener', []);
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testAbstractEventSubscriber()
    {
        $this->expectException(\InvalidArgumentException::class);
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testHotPathEvents()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        (new RegisterListenersPass())->setHotPathEvents(['event'])->process($container);

        $this->assertTrue($container->getDefinition('foo')->hasTag('container.hot_path'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNoPreloadEvents()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('bar')->addTag('kernel.event_listener', ['event' => 'cold_event']);
        $container->register('baz')
            ->addTag('kernel.event_listener', ['event' => 'event'])
            ->addTag('kernel.event_listener', ['event' => 'cold_event']);
        $container->register('event_dispatcher', 'stdClass');

        (new RegisterListenersPass())
            ->setHotPathEvents(['event'])
            ->setNoPreloadEvents(['cold_event'])
            ->process($container);

        $this->assertFalse($container->getDefinition('foo')->hasTag('container.no_preload'));
        $this->assertTrue($container->getDefinition('bar')->hasTag('container.no_preload'));
        $this->assertFalse($container->getDefinition('baz')->hasTag('container.no_preload'));
    }

    public function testHotPathEventsViaAddEventAliasesPass()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('event_dispatcher', 'stdClass');

        (new AddEventAliasesPass([], ['event']))->process($container);
        (new RegisterListenersPass())->process($container);

        $this->assertTrue($container->getDefinition('foo')->hasTag('container.hot_path'));
    }

    public function testNoPreloadEventsViaAddEventAliasesPass()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('bar')->addTag('kernel.event_listener', ['event' => 'cold_event']);
        $container->register('baz')
            ->addTag('kernel.event_listener', ['event' => 'event'])
            ->addTag('kernel.event_listener', ['event' => 'cold_event']);
        $container->register('event_dispatcher', 'stdClass');

        (new AddEventAliasesPass([], ['event'], ['cold_event']))->process($container);
        (new RegisterListenersPass())->process($container);

        $this->assertFalse($container->getDefinition('foo')->hasTag('container.no_preload'));
        $this->assertTrue($container->getDefinition('bar')->hasTag('container.no_preload'));
        $this->assertFalse($container->getDefinition('baz')->hasTag('container.no_preload'));
    }

    public function testRegisterListenersPassIsIdempotentAcrossContainers()
    {
        $first = new ContainerBuilder();
        $first->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $first->register('event_dispatcher', 'stdClass');
        $first->setParameter('event_dispatcher.hot_path_events', ['event']);

        $second = new ContainerBuilder();
        $second->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $second->register('event_dispatcher', 'stdClass');

        $pass = new RegisterListenersPass();
        $pass->process($first);
        $pass->process($second);

        $this->assertTrue($first->getDefinition('foo')->hasTag('container.hot_path'));
        $this->assertFalse($second->getDefinition('foo')->hasTag('container.hot_path'));
    }

    public function testMultipleAddEventAliasesPassMerge()
    {
        $container = new ContainerBuilder();

        $container->register('foo', SubscriberService::class)->addTag('kernel.event_subscriber', []);
        $container->register('bar')->addTag('kernel.event_listener', ['event' => 'cold_event']);
        $container->register('event_dispatcher', 'stdClass');

        // Two passes contribute different metadata (like two bundles would)
        (new AddEventAliasesPass([], ['event']))->process($container);
        (new AddEventAliasesPass([], [], ['cold_event']))->process($container);
        (new RegisterListenersPass())->process($container);

        $this->assertTrue($container->getDefinition('foo')->hasTag('container.hot_path'));
        $this->assertTrue($container->getDefinition('bar')->hasTag('container.no_preload'));
    }

    public function testEventSubscriberUnresolvableClassName()
    {
        $this->expectException(\InvalidArgumentException::class);
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
        $container->setParameter('event_dispatcher.event_aliases', [AliasedEvent::class => 'aliased_event']);

        $container->register('foo', \get_class(new class {
            public function onFooBar()
            {
            }
        }))->addTag('kernel.event_listener', ['event' => 'foo.bar']);
        $container->register('bar', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo.bar']);
        $container->register('baz', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event']);
        $container->register('zar', \get_class(new class {
            public function onFooBarZar()
            {
            }
        }))->addTag('kernel.event_listener', ['event' => 'foo.bar_zar']);
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
            [
                'addListener',
                [
                    'foo.bar_zar',
                    [new ServiceClosureArgument(new Reference('zar')), 'onFooBarZar'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    public function testItThrowsAnExceptionIfTagIsMissingMethodAndClassHasNoValidMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('None of the "onFooBar" or "__invoke" methods exist for the service "foo". Please define the "method" attribute on "kernel.event_listener" tags.');

        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class)->addTag('kernel.event_listener', ['event' => 'foo.bar']);
        $container->register('event_dispatcher', \stdClass::class);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testTaggedInvokableEventListener()
    {
        $container = $this->createContainerBuilder();
        $container->register('foo', TaggedInvokableListener::class)->setAutoconfigured(true);
        $container->register('event_dispatcher', \stdClass::class);

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new RegisterListenersPass())->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    CustomEvent::class,
                    [new ServiceClosureArgument(new Reference('foo')), '__invoke'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, \array_slice($definition->getMethodCalls(), 0, \count($expectedCalls)));
    }

    public function testTaggedMultiEventListener()
    {
        $container = $this->createContainerBuilder();

        $container->register('foo', TaggedMultiListener::class)->setAutoconfigured(true);
        $container->register('event_dispatcher', \stdClass::class);

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new RegisterListenersPass())->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    CustomEvent::class,
                    [new ServiceClosureArgument(new Reference('foo')), 'onCustomEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'foo',
                    [new ServiceClosureArgument(new Reference('foo')), 'onFoo'],
                    42,
                ],
            ],
            [
                'addListener',
                [
                    'bar',
                    [new ServiceClosureArgument(new Reference('foo')), 'onBarEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    'baz',
                    [new ServiceClosureArgument(new Reference('foo')), 'onBazEvent'],
                    0,
                ],
            ],
        ];
        $this->assertEquals($expectedCalls, \array_slice($definition->getMethodCalls(), 0, \count($expectedCalls)));
    }

    public function testTaggedMethodUnionTypeEventListener()
    {
        $container = $this->createContainerBuilder();

        $container->register('foo', TaggedUnionTypeListener::class)->setAutoconfigured(true);
        $container->register('event_dispatcher', \stdClass::class);

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new RegisterListenersPass())->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = [
            [
                'addListener',
                [
                    CustomEvent::class,
                    [new ServiceClosureArgument(new Reference('foo')), 'onUnionEvent'],
                    0,
                ],
            ],
            [
                'addListener',
                [
                    DummyEvent::class,
                    [new ServiceClosureArgument(new Reference('foo')), 'onUnionEvent'],
                    0,
                ],
            ],
        ];

        $this->assertEquals($expectedCalls, \array_slice($definition->getMethodCalls(), 0, \count($expectedCalls)));
    }

    public function testAliasedEventListener()
    {
        $container = new ContainerBuilder();
        $eventAliases = [AliasedEvent::class => 'aliased_event'];
        $container->setParameter('event_dispatcher.event_aliases', $eventAliases);
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => AliasedEvent::class, 'method' => 'onEvent']);
        $container->register('bar', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => CustomEvent::class, 'method' => 'onEvent']);
        $container->register('event_dispatcher');

        $customEventAlias = [CustomEvent::class => 'custom_event'];
        $eventAliasPass = new AddEventAliasesPass($customEventAlias);
        $eventAliasPass->process($container);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $this->assertTrue($container->hasParameter('event_dispatcher.event_aliases'));
        $this->assertSame(array_merge($eventAliases, $customEventAlias), $container->getParameter('event_dispatcher.event_aliases'));

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

    public function testOmitEventNameOnTypedListener()
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

    public function testOmitEventNameOnUntypedListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener', ['method' => 'onEvent']);
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testOmitEventNameAndMethodOnUntypedListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', InvokableListenerService::class)->addTag('kernel.event_listener');
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testOmitEventNameAndMethodOnGenericListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', GenericListener::class)->addTag('kernel.event_listener');
        $container->register('event_dispatcher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "foo" must define the "event" attribute on "kernel.event_listener" tags.');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testOmitEventNameOnSubscriber()
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

    #[RequiresMethod(ServicesBundle::class, 'build')]
    public function testDecoratingAListenerRegistersTheDecoratorAsListener()
    {
        $container = new ContainerBuilder();
        new ServicesBundle()->build($container);

        $container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $container->register('listener', TaggedInvokableListener::class)
            ->setPublic(true)
            ->addTag('kernel.event_listener', ['event' => CustomEvent::class]);
        $container->register('decorator', DecoratingListener::class)
            ->setPublic(true)
            ->setArguments([new Reference('decorator.inner')])
            ->setDecoratedService('listener');

        $container->compile();

        $listeners = [];
        foreach ($container->getDefinition('event_dispatcher')->getMethodCalls() as [$method, $arguments]) {
            if ('addListener' === $method) {
                $listeners[] = (string) $arguments[1][0]->getValues()[0];
            }
        }

        $this->assertSame(['decorator'], $listeners);
    }

    #[RequiresMethod(ServicesBundle::class, 'build')]
    public function testDecoratorThatIsAlsoAnEventSubscriberStaysRegistered()
    {
        $container = new ContainerBuilder();
        new ServicesBundle()->build($container);
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');

        $container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $container->register('decorated', \stdClass::class);
        $container->register('decorator', SubscribingDecorator::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setArguments([new Reference('decorator.inner')])
            ->setDecoratedService('decorated');

        $container->compile();

        $listeners = [];
        foreach ($container->getDefinition('event_dispatcher')->getMethodCalls() as [$method, $arguments]) {
            if ('addListener' === $method) {
                $listeners[] = (string) $arguments[1][0]->getValues()[0];
            }
        }

        $this->assertSame(['decorator'], $listeners);
    }

    public function testListenersWithoutConstraintsAreRegisteredInTagOrder()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'priority' => 10]);
        $container->register('c', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);

        (new RegisterListenersPass())->process($container);

        $expectedCalls = [
            ['addListener', ['foo', [new ServiceClosureArgument(new Reference('a')), 'onEvent'], 0]],
            ['addListener', ['foo', [new ServiceClosureArgument(new Reference('b')), 'onEvent'], 10]],
            ['addListener', ['foo', [new ServiceClosureArgument(new Reference('c')), 'onEvent'], 0]],
        ];

        $this->assertEquals($expectedCalls, $container->getDefinition('event_dispatcher')->getMethodCalls());
    }

    public function testABeforeConstraintReordersListenersOfTheSamePriority()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('c', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'before' => 'a']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'c', 0], ['foo', 'a', 0], ['foo', 'b', 0]], $this->getListenerCalls($container));
    }

    public function testAListenerCanBeInsertedBetweenTwoOthers()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('x', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'bar', 'method' => 'onEvent']);
        $container->register('c', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('e', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'after' => 'b', 'before' => 'c']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'b', 0], ['bar', 'x', 0], ['foo', 'e', 0], ['foo', 'c', 0]], $this->getListenerCalls($container));
    }

    public function testAConstraintRaisesThePriorityOfTheConstrainedListener()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'priority' => 10]);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'before' => 'a']);
        $container->register('d', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'priority' => 5]);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'b', 10], ['foo', 'a', 10], ['foo', 'd', 5]], $this->getListenerCalls($container));
    }

    public function testConstraintsTargetingAnUnknownListenerAreIgnored()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'after' => 'from_a_bundle_that_is_not_installed']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'a', 0], ['foo', 'b', 0]], $this->getListenerCalls($container));
    }

    public function testAListenerCanBeOrderedAfterASubscriber()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'after' => 'subscriber']);
        $container->register('subscriber', SubscriberService::class)->addTag('kernel.event_subscriber');

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'subscriber', 0], ['event', 'a', 0]], $this->getListenerCalls($container));
    }

    public function testAConstraintCanTargetAClass()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'after' => SubscriberService::class]);
        $container->register('subscriber', SubscriberService::class)->addTag('kernel.event_subscriber');

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'subscriber', 0], ['event', 'a', 0]], $this->getListenerCalls($container));
    }

    public function testSeveralRegistrationsOfTheSameServiceKeepTheirOwnPlace()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'priority' => -200, 'before' => 'b']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent']);
        $container->register('subscriber', MultiPrioritySubscriber::class)->addTag('kernel.event_subscriber');

        (new RegisterListenersPass())->process($container);

        $expectedCalls = [['event', 'subscriber', 100], ['event', 'a', 0], ['event', 'b', 0], ['event', 'subscriber', -100]];

        $this->assertSame($expectedCalls, $this->getListenerCalls($container));
    }

    public function testConstraintsAreScopedToTheirDispatcher()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('other_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'dispatcher' => 'other_dispatcher']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'before' => 'a']);
        $container->register('c', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'b', 0], ['foo', 'c', 0]], $this->getListenerCalls($container));
        $this->assertSame([['foo', 'a', 0]], $this->getListenerCalls($container, 'other_dispatcher'));
    }

    public function testCyclicConstraintsAreReported()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'before' => 'b']);
        $container->register('b', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent', 'before' => 'a']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "before"/"after" constraints for event "foo": cycle detected in the "before"/"after" constraints: "a" -> "b" -> "a".');

        (new RegisterListenersPass())->process($container);
    }

    public function testBeforeAndAfterAreReadFromTheAttribute()
    {
        $container = $this->createContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'foo', 'method' => 'onEvent']);
        $container->register('b', OrderedListener::class)->setAutoconfigured(true);

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new RegisterListenersPass())->process($container);

        $this->assertSame([['foo', 'b', 0], ['foo', 'a', 0]], $this->getListenerCalls($container));
    }

    public function testAConstraintCanTargetASingleMethodOfAListener()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('sub', MultiPrioritySubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'priority' => -200, 'before' => 'sub::onLate']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'sub', 100], ['event', 'a', -100], ['event', 'sub', -100]], $this->getListenerCalls($container));
    }

    public function testTargetingTheWholeServiceOutranksAllOfItsMethods()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('sub', MultiPrioritySubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'before' => 'sub']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'a', 100], ['event', 'sub', 100], ['event', 'sub', -100]], $this->getListenerCalls($container));
    }

    public function testAMethodTargetCanUseTheClassName()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('sub', MultiPrioritySubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'priority' => -200, 'before' => MultiPrioritySubscriber::class.'::onLate']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'sub', 100], ['event', 'a', -100], ['event', 'sub', -100]], $this->getListenerCalls($container));
    }

    public function testAMethodTargetOnAKnownListenerMustExist()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('sub', MultiPrioritySubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'before' => 'sub::onLat']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "before" constraint on listener "a": "sub" does not listen to event "event" with method "onLat".');

        (new RegisterListenersPass())->process($container);
    }

    public function testAMethodTargetOnAnAbsentServiceIsIgnored()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'before' => 'from_an_absent_bundle::onWhatever']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'a', 0]], $this->getListenerCalls($container));
    }

    public function testAMethodTargetResolvesAgainstASubscriberUsingNamedKeys()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', \stdClass::class);
        $container->register('sub', NamedKeysSubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('a', InvokableListenerService::class)->addTag('kernel.event_listener', ['event' => 'event', 'method' => 'onEvent', 'priority' => -200, 'before' => 'sub::onLate']);

        (new RegisterListenersPass())->process($container);

        $this->assertSame([['event', 'sub', 100], ['event', 'a', -100], ['event', 'sub', -100]], $this->getListenerCalls($container));
    }

    private function getListenerCalls(ContainerBuilder $container, string $dispatcher = 'event_dispatcher'): array
    {
        $calls = [];
        foreach ($container->getDefinition($dispatcher)->getMethodCalls() as [$method, $arguments]) {
            if ('addListener' === $method) {
                $calls[] = [$arguments[0], (string) $arguments[1][0]->getValues()[0], $arguments[2]];
            }
        }

        return $calls;
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });

        return $container;
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

final class DecoratingListener
{
    public function __construct(private TaggedInvokableListener $inner)
    {
    }

    public function __invoke(CustomEvent $event): void
    {
    }
}

final class SubscribingDecorator implements EventSubscriberInterface
{
    public function __construct(private object $inner)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return ['some_event' => 'onSomeEvent'];
    }

    public function onSomeEvent(): void
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

#[AsEventListener(event: 'foo', method: 'onEvent', before: 'a')]
final class OrderedListener
{
    public function onEvent(): void
    {
    }
}

final class MultiPrioritySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['event' => [['onEarly', 100], ['onLate', -100]]];
    }

    public function onEarly(): void
    {
    }

    public function onLate(): void
    {
    }
}

final class NamedKeysSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['event' => [
            ['method' => 'onEarly', 'priority' => 100],
            ['method' => 'onLate', 'priority' => -100],
        ]];
    }

    public function onEarly(): void
    {
    }

    public function onLate(): void
    {
    }
}
