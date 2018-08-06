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
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RegisterEventListenersAndSubscribersPassTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnAbstractTaggedSubscriber()
    {
        $container = $this->createBuilder();

        $abstractDefinition = new Definition('stdClass');
        $abstractDefinition->setAbstract(true);
        $abstractDefinition->addTag('doctrine.event_subscriber');

        $container->setDefinition('a', $abstractDefinition);

        $this->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnAbstractTaggedListener()
    {
        $container = $this->createBuilder();

        $abstractDefinition = new Definition('stdClass');
        $abstractDefinition->setAbstract(true);
        $abstractDefinition->addTag('doctrine.event_listener', array('event' => 'test'));

        $container->setDefinition('a', $abstractDefinition);

        $this->process($container);
    }

    public function testProcessEventListenersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->setPublic(false)
            ->addTag('doctrine.event_listener', array(
                'event' => 'bar',
            ))
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
                'priority' => -5,
            ))
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo_bar',
                'priority' => 3,
            ))
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
            ))
        ;
        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo_bar',
                'priority' => 4,
            ))
        ;

        $this->process($container);
        $eventManagerDef = $container->getDefinition('doctrine.dbal.default_connection.event_manager');
        $methodCalls = $eventManagerDef->getMethodCalls();

        $this->assertEquals(
            array(
                array('addEventListener', array(array('foo_bar'), 'c')),
                array('addEventListener', array(array('foo_bar'), 'a')),
                array('addEventListener', array(array('bar'), 'a')),
                array('addEventListener', array(array('foo'), 'b')),
                array('addEventListener', array(array('foo'), 'a')),
            ),
            $methodCalls
        );

        $serviceLocatorDef = $container->getDefinition((string) $eventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            array(
                'c' => new ServiceClosureArgument(new Reference('c')),
                'a' => new ServiceClosureArgument(new Reference('a')),
                'b' => new ServiceClosureArgument(new Reference('b')),
            ),
            $serviceLocatorDef->getArgument(0)
        );
    }

    public function testProcessEventListenersWithMultipleConnections()
    {
        $container = $this->createBuilder(true);

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'onFlush',
            ))
        ;

        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'onFlush',
                'connection' => 'default',
            ))
        ;

        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'onFlush',
                'connection' => 'second',
            ))
        ;

        $this->process($container);

        $eventManagerDef = $container->getDefinition('doctrine.dbal.default_connection.event_manager');

        // first connection
        $this->assertEquals(
            array(
                array('addEventListener', array(array('onFlush'), 'a')),
                array('addEventListener', array(array('onFlush'), 'b')),
            ),
            $eventManagerDef->getMethodCalls()
        );

        $serviceLocatorDef = $container->getDefinition((string) $eventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            array(
                'a' => new ServiceClosureArgument(new Reference('a')),
                'b' => new ServiceClosureArgument(new Reference('b')),
            ),
            $serviceLocatorDef->getArgument(0)
        );

        // second connection
        $secondEventManagerDef = $container->getDefinition('doctrine.dbal.second_connection.event_manager');
        $this->assertEquals(
            array(
                array('addEventListener', array(array('onFlush'), 'a')),
                array('addEventListener', array(array('onFlush'), 'c')),
            ),
            $secondEventManagerDef->getMethodCalls()
        );

        $serviceLocatorDef = $container->getDefinition((string) $secondEventManagerDef->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDef->getClass());
        $this->assertEquals(
            array(
                'a' => new ServiceClosureArgument(new Reference('a')),
                'c' => new ServiceClosureArgument(new Reference('c')),
            ),
            $serviceLocatorDef->getArgument(0)
        );
    }

    public function testProcessEventSubscribersWithMultipleConnections()
    {
        $container = $this->createBuilder(true);

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'event' => 'onFlush',
            ))
        ;

        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'event' => 'onFlush',
                'connection' => 'default',
            ))
        ;

        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'event' => 'onFlush',
                'connection' => 'second',
            ))
        ;

        $this->process($container);

        $this->assertEquals(
            array(
                array('addEventSubscriber', array(new Reference('a'))),
                array('addEventSubscriber', array(new Reference('b'))),
            ),
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
        );

        $this->assertEquals(
            array(
                array('addEventSubscriber', array(new Reference('a'))),
                array('addEventSubscriber', array(new Reference('c'))),
            ),
            $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getMethodCalls()
        );
    }

    public function testProcessEventSubscribersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_subscriber')
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 5,
            ))
        ;
        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;
        $container
            ->register('d', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;
        $container
            ->register('e', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;

        $this->process($container);

        $this->assertEquals(
            array(
                array('addEventSubscriber', array(new Reference('c'))),
                array('addEventSubscriber', array(new Reference('d'))),
                array('addEventSubscriber', array(new Reference('e'))),
                array('addEventSubscriber', array(new Reference('b'))),
                array('addEventSubscriber', array(new Reference('a'))),
            ),
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
        );
    }

    public function testProcessNoTaggedServices()
    {
        $container = $this->createBuilder(true);

        $this->process($container);

        $this->assertEquals(array(), $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls());

        $this->assertEquals(array(), $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getMethodCalls());
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine');
        $pass->process($container);
    }

    private function createBuilder($multipleConnections = false)
    {
        $container = new ContainerBuilder();

        $connections = array('default' => 'doctrine.dbal.default_connection');

        $container->register('doctrine.dbal.default_connection.event_manager', 'stdClass')
            ->addArgument(new Reference('service_container'));
        $container->register('doctrine.dbal.default_connection', 'stdClass');

        if ($multipleConnections) {
            $container->register('doctrine.dbal.second_connection.event_manager', 'stdClass')
                ->addArgument(new Reference('service_container'));
            $container->register('doctrine.dbal.second_connection', 'stdClass');
            $connections['second'] = 'doctrine.dbal.second_connection';
        }

        $container->setParameter('doctrine.connections', $connections);

        return $container;
    }
}
