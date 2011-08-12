<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterEventListenersAndSubscribersPassTest extends \PHPUnit_Framework_TestCase
{
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
        $this->assertEquals(array('c', 'd', 'e', 'b', 'a'), $this->getServiceOrder($container, 'addEventSubscriber'));
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new RegisterEventListenersAndSubscribersPass();
        $pass->process($container);
    }

    private function getServiceOrder(ContainerBuilder $container, $method)
    {
        $order = array();
        foreach ($container->getDefinition('event_manager')->getMethodCalls() as $call) {
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

    private function createBuilder()
    {
        $container = new ContainerBuilder();
        $container->register('doctrine', 'stdClass');
        $container->register('event_manager', 'stdClass');
        $container
            ->register('database_connection', 'stdClass')
            ->setArguments(array(null, null, 'event_manager'))
        ;
        $container->setParameter('doctrine.connections', array('default' => 'database_connection'));

        return $container;
    }
}