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

use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RegisterEventListenersAndSubscribersPassTest extends \PHPUnit_Framework_TestCase
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
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
                'priority' => -5,
            ))
            ->addTag('doctrine.event_listener', array(
                'event' => 'bar',
            ))
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
            ))
        ;

        $this->process($container);
        $this->assertEquals(array('b', 'a'), $this->getServiceOrder($container, 'addEventListener'));

        $calls = $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls();
        $this->assertEquals(array('foo', 'bar'), $calls[1][1][0]);
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
        $this->process($container);

        $callsDefault = $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls();

        $this->assertEquals('addEventListener', $callsDefault[0][0]);
        $this->assertEquals(array('onFlush'), $callsDefault[0][1][0]);

        $callsSecond = $container->getDefinition('doctrine.dbal.second_connection.event_manager')->getMethodCalls();
        $this->assertEquals($callsDefault, $callsSecond);
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
        $serviceOrder = $this->getServiceOrder($container, 'addEventSubscriber');
        $unordered = array_splice($serviceOrder, 0, 3);
        sort($unordered);
        $this->assertEquals(array('c', 'd', 'e'), $unordered);
        $this->assertEquals(array('b', 'a'), $serviceOrder);
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine');
        $pass->process($container);
    }

    private function getServiceOrder(ContainerBuilder $container, $method)
    {
        $order = array();
        foreach ($container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls() as $call) {
            list($name, $arguments) = $call;
            if ($method !== $name) {
                continue;
            }

            if ('addEventListener' === $name) {
                $order[] = (string) $arguments[1];
                continue;
            }

            $order[] = (string) $arguments[0];
        }

        return $order;
    }

    private function createBuilder($multipleConnections = false)
    {
        $container = new ContainerBuilder();

        $connections = array('default' => 'doctrine.dbal.default_connection');

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
