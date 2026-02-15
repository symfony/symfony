<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerAttributesListenerPass;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\KernelEvents;

class ControllerAttributesListenerPassTest extends TestCase
{
    public function testCollectsAttributeListenersByKernelEvent()
    {
        $container = new ContainerBuilder();

        $dispatcher = new Definition();
        $dispatcher->addMethodCall('addListener', [KernelEvents::CONTROLLER.'.'.TestAttribute::class, [new Reference('listener.service'), 'onKernelController'], 0]);
        $dispatcher->addMethodCall('addListener', [KernelEvents::RESPONSE.'.'.AnotherAttribute::class, [new Reference('listener.service'), 'onKernelResponse'], 0]);
        $dispatcher->addMethodCall('addListener', [KernelEvents::REQUEST, [new Reference('other.service'), 'onKernelRequest'], 0]);
        $container->setDefinition('event_dispatcher', $dispatcher);

        $listener = new Definition(ControllerAttributesListener::class, [[]]);
        $container->setDefinition('kernel.controller_attributes_listener', $listener);

        $pass = new ControllerAttributesListenerPass();
        $pass->process($container);

        $this->assertSame([
            KernelEvents::CONTROLLER => [TestAttribute::class => true],
            KernelEvents::RESPONSE => [AnotherAttribute::class => true],
        ], $listener->getArgument(0));
    }

    public function testSetsEmptyConfigurationWhenNoAttributeListenersAreRegistered()
    {
        $container = new ContainerBuilder();

        $dispatcher = new Definition();
        $dispatcher->addMethodCall('addListener', [KernelEvents::REQUEST, [new Reference('listener.service'), 'onKernelRequest'], 0]);
        $container->setDefinition('event_dispatcher', $dispatcher);

        $listener = new Definition(ControllerAttributesListener::class, [[]]);
        $container->setDefinition('kernel.controller_attributes_listener', $listener);

        $pass = new ControllerAttributesListenerPass();
        $pass->process($container);

        $this->assertSame([], $listener->getArgument(0));
    }
}

#[\Attribute]
class TestAttribute
{
}

#[\Attribute]
class AnotherAttribute extends TestAttribute
{
}
