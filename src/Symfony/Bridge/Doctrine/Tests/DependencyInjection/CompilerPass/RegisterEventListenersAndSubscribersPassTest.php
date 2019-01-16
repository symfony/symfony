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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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
        $abstractDefinition->addTag('doctrine.event_listener', ['event' => 'test']);

        $container->setDefinition('a', $abstractDefinition);

        $this->process($container);
    }

    public function testProcessEventListenersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->setPublic(false)
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
                'lazy' => true,
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
        $methodCalls = $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls();

        $this->assertEquals(
            [
                ['addEventListener', [['foo_bar'], new Reference('c')]],
                ['addEventListener', [['foo_bar'], new Reference('a')]],
                ['addEventListener', [['bar'], new Reference('a')]],
                ['addEventListener', [['foo'], new Reference('b')]],
                ['addEventListener', [['foo'], new Reference('a')]],
            ],
            $methodCalls
        );

        // not lazy so must be reference
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $methodCalls[0][1][1]);

        // lazy so id instead of reference and must mark service public
        $this->assertSame('a', $methodCalls[1][1][1]);
        $this->assertTrue($container->getDefinition('a')->isPublic());
    }

    public function testProcessEventListenersWithMultipleConnections()
    {
        $container = $this->createBuilder(true);

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

        $this->process($container);

        $this->assertEquals(
            [
                ['addEventListener', [['onFlush'], new Reference('a')]],
                ['addEventListener', [['onFlush'], new Reference('b')]],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
        );

        $this->assertEquals(
            [
                ['addEventListener', [['onFlush'], new Reference('a')]],
                ['addEventListener', [['onFlush'], new Reference('c')]],
            ],
            $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getMethodCalls()
        );
    }

    public function testProcessEventSubscribersWithMultipleConnections()
    {
        $container = $this->createBuilder(true);

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'event' => 'onFlush',
            ])
        ;

        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'event' => 'onFlush',
                'connection' => 'default',
            ])
        ;

        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'event' => 'onFlush',
                'connection' => 'second',
            ])
        ;

        $this->process($container);

        $this->assertEquals(
            [
                ['addEventSubscriber', [new Reference('a')]],
                ['addEventSubscriber', [new Reference('b')]],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
        );

        $this->assertEquals(
            [
                ['addEventSubscriber', [new Reference('a')]],
                ['addEventSubscriber', [new Reference('c')]],
            ],
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
            ->addTag('doctrine.event_subscriber', [
                'priority' => 5,
            ])
        ;
        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'priority' => 10,
            ])
        ;
        $container
            ->register('d', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'priority' => 10,
            ])
        ;
        $container
            ->register('e', 'stdClass')
            ->addTag('doctrine.event_subscriber', [
                'priority' => 10,
            ])
        ;

        $this->process($container);

        $this->assertEquals(
            [
                ['addEventSubscriber', [new Reference('c')]],
                ['addEventSubscriber', [new Reference('d')]],
                ['addEventSubscriber', [new Reference('e')]],
                ['addEventSubscriber', [new Reference('b')]],
                ['addEventSubscriber', [new Reference('a')]],
            ],
            $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls()
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

        $container->register('doctrine.dbal.default_connection.event_manager', 'stdClass');
        $container->register('doctrine.dbal.default_connection', 'stdClass');

        if ($multipleConnections) {
            $container->register('doctrine.dbal.second_connection.event_manager', 'stdClass');
            $container->register('doctrine.dbal.second_connection', 'stdClass');
            $connections['second'] = 'doctrine.dbal.second_connection';
        }

        $container->setParameter('doctrine.connections', $connections);

        return $container;
    }
}
