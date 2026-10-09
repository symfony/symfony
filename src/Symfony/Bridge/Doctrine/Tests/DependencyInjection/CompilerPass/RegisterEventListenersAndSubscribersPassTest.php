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

use Doctrine\Common\EventManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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

    public function testListenerWithoutPriorityIsPlacedByItsConstraints()
    {
        $container = $this->createBuilder();

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 10]);
        $container->register('x', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'bar', 'priority' => 5]);
        $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('c', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'before' => 'a']);
        $container->register('e', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => null, 'after' => ['f']]);
        $container->register('f', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => -5]);

        $this->process($container);

        $this->assertEquals(
            [
                [['foo'], 'c'],
                [['bar'], 'x'],
                [['foo'], 'a'],
                [['foo'], 'b'],
                [['foo'], 'f'],
                [['foo'], 'e'],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
    }

    public function testConstraintsReorderListenersSharingAPriority()
    {
        $container = $this->createBuilder();

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 5]);
        $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 5, 'before' => 'a']);
        $container->register('c', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo']);

        $this->process($container);

        $this->assertEquals(
            [
                [['foo'], 'b'],
                [['foo'], 'a'],
                [['foo'], 'c'],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
    }

    public function testConstraintCrossingAPriorityThrows()
    {
        $container = $this->createBuilder();

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 10]);
        $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 0, 'before' => 'a']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot order the listeners of event "foo" on connection "default": the priority of "b" (0) contradicts its "before" constraint on "a" (10): raise it to 10 or more, remove it, or drop the constraint.');

        $this->process($container);
    }

    public function testConstraintTargetingAClass()
    {
        $container = $this->createBuilder();

        $container->register('a', \ArrayObject::class)->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('b.parent', \ArrayIterator::class)->setAbstract(true);
        $container->setDefinition('b', new ChildDefinition('b.parent'))->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('c', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'before' => [\ArrayObject::class, \ArrayIterator::class]]);

        $this->process($container);

        $this->assertEquals(
            [
                [['foo'], 'c'],
                [['foo'], 'a'],
                [['foo'], 'b'],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
    }

    public function testConstraintOnAbsentTargetIsIgnored()
    {
        $container = $this->createBuilder();

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'before' => 'missing', 'after' => 'App\Missing']);
        $container->register('c', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'bar']);

        $this->process($container);

        $this->assertEquals(
            [
                [['foo'], 'a'],
                [['foo'], 'b'],
                [['bar'], 'c'],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
    }

    public function testConstraintsAreScopedPerConnectionAndEvent()
    {
        $container = $this->createBuilder(true);

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', ['event' => 'foo', 'connection' => 'second'])
            ->addTag('doctrine.event_listener', ['event' => 'bar']);
        $container->register('c', 'stdClass')
            ->addTag('doctrine.event_listener', ['event' => 'foo', 'before' => 'b'])
            ->addTag('doctrine.event_listener', ['event' => 'bar']);

        $this->process($container);

        $this->assertEquals(
            [
                [['foo'], 'a'],
                [['bar'], 'b'],
                [['foo'], 'c'],
                [['bar'], 'c'],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
        $this->assertEquals(
            [
                [['foo'], 'a'],
                [['foo'], 'c'],
                [['bar'], 'b'],
                [['foo'], 'b'],
                [['bar'], 'c'],
            ],
            $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getArgument(1)
        );
    }

    public function testConstraintsReorderAddEventListenerCalls()
    {
        $container = $this->createBuilder();
        $container->register('doctrine.dbal.default_connection.event_manager', EventManager::class);

        $container->register('a', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo']);
        $container->register('x', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'bar']);
        $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'before' => 'a']);

        $this->process($container);

        $this->assertEquals(
            [
                ['addEventListener', [['foo'], new Reference('b')]],
                ['addEventListener', [['bar'], new Reference('x')]],
                ['addEventListener', [['foo'], new Reference('a')]],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
        );
    }

    public function testListenersWithoutConstraintsKeepTheirOrder()
    {
        $withoutConstraints = $this->createBuilder(true);
        $withNoopConstraints = $this->createBuilder(true);

        foreach ([$withoutConstraints, $withNoopConstraints] as $i => $container) {
            $container->register('a', 'stdClass')
                ->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => null])
                ->addTag('doctrine.event_listener', ['event' => 'bar', 'priority' => -5, 'connection' => 'second']);
            $container->register('b', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'priority' => 5]);
            $container->register('c', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'foo', 'connection' => 'default'] + ($i ? ['before' => [], 'after' => 'missing'] : []));
            $container->register('d', 'stdClass')->addTag('doctrine.event_listener', ['event' => 'bar', 'priority' => 3] + ($i ? ['before' => 'a'] : []));
        }

        $this->process($withoutConstraints);
        $this->process($withNoopConstraints);

        $this->assertEquals(
            [
                [['foo'], 'b'],
                [['bar'], 'd'],
                [['foo'], 'a'],
                [['foo'], 'c'],
            ],
            $withoutConstraints->getDefinition('doctrine.dbal.default_connection.event_manager')->getArgument(1)
        );
        $this->assertEquals(
            [
                [['foo'], 'b'],
                [['bar'], 'd'],
                [['foo'], 'a'],
                [['bar'], 'a'],
            ],
            $withoutConstraints->getDefinition('doctrine.dbal.second_connection.event_manager')->getArgument(1)
        );

        foreach (['default', 'second'] as $connection) {
            $id = 'doctrine.dbal.'.$connection.'_connection.event_manager';
            $this->assertSame($withoutConstraints->getDefinition($id)->getArgument(1), $withNoopConstraints->getDefinition($id)->getArgument(1));
            $this->assertSame((string) $withoutConstraints->getDefinition($id)->getArgument(0), (string) $withNoopConstraints->getDefinition($id)->getArgument(0));
        }
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
