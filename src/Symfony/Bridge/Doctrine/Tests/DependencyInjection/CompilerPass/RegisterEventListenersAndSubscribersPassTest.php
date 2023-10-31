<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RegisterEventListenersAndSubscribersPassTest extends TestCase
{
    public function testExceptionOnAbstractTaggedListener()
    {
        $container = $this->createBuilder();

        $abstractDefinition = new Definition('stdClass');
        $abstractDefinition->setAbstract(true);
        $abstractDefinition->addTag('doctrine.event_listener', ['event' => 'test']);

        $container->setDefinition('a', $abstractDefinition);

        $this->expectException(\InvalidArgumentException::class);

        $this->process($container);
    }

    public function testProcessEventListenersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'bar',
            ])
            ->addTag('doctrine.event_listener', [
                'event' => 'foo',
                'priority' => -5,
            ])
            ->addTag('doctrine.event_listener', [
                'event' => 'foo_bar',
                'priority' => 3,
            ])
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'foo',
            ])
        ;
        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'foo_bar',
                'priority' => 4,
            ])
        ;

        $this->process($container);
        $eventManagerDef = $container->getDefinition('doctrine.dbal.default_connection.event_manager');

        $this->assertEquals(
            [
                [['foo_bar'], 'c'],
                [['foo_bar'], 'a'],
                [['bar'], 'a'],
                [['foo'], 'b'],
                [['foo'], 'a'],
            ],
            $eventManagerDef->getArgument(1)
        );
        $this->assertEquals([], $eventManagerDef->getMethodCalls());

        $serviceLocatorDef = $container->getDefinition((string) $eventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            [
                'c' => new ServiceClosureArgument(new Reference('c')),
                'a' => new ServiceClosureArgument(new Reference('a')),
                'b' => new ServiceClosureArgument(new Reference('b')),
            ],
            $serviceLocatorDef->getArgument(0)
        );
    }

    public function testProcessEventListenersWithMultipleConnections()
    {
        $container = $this->createBuilder(true);

        $container->setParameter('connection_param', 'second');

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'onFlush',
            ])
        ;

        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'onFlush',
                'connection' => 'default',
            ])
        ;

        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'onFlush',
                'connection' => 'second',
            ])
        ;

        $container
            ->register('d', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'onFlush',
                'connection' => '%connection_param%',
            ])
        ;

        $this->process($container);

        $eventManagerDef = $container->getDefinition('doctrine.dbal.default_connection.event_manager');

        // first connection
        $this->assertEquals(
            [
                [['onFlush'], 'a'],
                [['onFlush'], 'b'],
            ],
            $eventManagerDef->getArgument(1)
        );
        $this->assertEquals([], $eventManagerDef->getMethodCalls());

        $serviceLocatorDef = $container->getDefinition((string) $eventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            [
                'a' => new ServiceClosureArgument(new Reference('a')),
                'b' => new ServiceClosureArgument(new Reference('b')),
            ],
            $serviceLocatorDef->getArgument(0)
        );

        // second connection
        $secondEventManagerDef = $container->getDefinition('doctrine.dbal.second_connection.event_manager');
        $this->assertEquals(
            [
                [['onFlush'], 'a'],
                [['onFlush'], 'c'],
                [['onFlush'], 'd'],
            ],
            $secondEventManagerDef->getArgument(1)
        );
        $this->assertEquals([], $secondEventManagerDef->getMethodCalls());

        $serviceLocatorDef = $container->getDefinition((string) $secondEventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            [
                'a' => new ServiceClosureArgument(new Reference('a')),
                'c' => new ServiceClosureArgument(new Reference('c')),
                'd' => new ServiceClosureArgument(new Reference('d')),
            ],
            $serviceLocatorDef->getArgument(0)
        );
    }

    public function testSubscribersAreSkippedIfListenerDefinedForSameDefinition()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'bar',
                'priority' => 3,
            ])
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', [
                'event' => 'bar',
            ])
            ->addTag('doctrine.event_listener', [
                'event' => 'foo',
                'priority' => -5,
            ])
            ->addTag('doctrine.event_subscriber')
        ;
        $this->process($container);

        $eventManagerDef = $container->getDefinition('doctrine.dbal.default_connection.event_manager');

        $this->assertEquals(
            [
                [['bar'], 'a'],
                [['bar'], 'b'],
                [['foo'], 'b'],
            ],
            $eventManagerDef->getArgument(1)
        );
    }

    public function testProcessNoTaggedServices()
    {
        $container = $this->createBuilder(true);

        $this->process($container);

        $this->assertEquals([], $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls());

        $this->assertEquals([], $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getMethodCalls());
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine');
        $pass->process($container);
    }

    private function createBuilder($multipleConnections = false)
    {
        $container = new ContainerBuilder();

        $connections = ['default' => 'doctrine.dbal.default_connection'];

        $container->register('doctrine.dbal.default_connection.event_manager', ContainerAwareEventManager::class)
            ->addArgument(new Reference('service_container'));
        $container->register('doctrine.dbal.default_connection', 'stdClass');

        if ($multipleConnections) {
            $container->register('doctrine.dbal.second_connection.event_manager', ContainerAwareEventManager::class)
                ->addArgument(new Reference('service_container'));
            $container->register('doctrine.dbal.second_connection', 'stdClass');
            $connections['second'] = 'doctrine.dbal.second_connection';
        }

        $container->setParameter('doctrine.connections', $connections);

        return $container;
    }
}
