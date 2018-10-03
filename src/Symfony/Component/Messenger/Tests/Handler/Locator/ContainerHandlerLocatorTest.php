<?php

namespace Symfony\Component\Messenger\Tests\Handler\Locator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Handler\Locator\ContainerHandlerLocator;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;

class ContainerHandlerLocatorTest extends TestCase
{
    public function testItLocatesHandlerUsingTheMessageClass()
    {
        $handler = function () {};

        $container = new Container();
        $container->set('handler.'.DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->resolve(new DummyMessage('Hey'));

        $this->assertSame($handler, $resolvedHandler);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\NoHandlerForMessageException
     * @expectedExceptionMessage No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
     */
    public function testThrowsNoHandlerException()
    {
        $locator = new ContainerHandlerLocator(new Container());
        $locator->resolve(new DummyMessage('Hey'));
    }

    public function testResolveMessageViaTheirInterface()
    {
        $handler = function () {};

        $container = new Container();
        $container->set('handler.'.DummyMessageInterface::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->resolve(new DummyMessage('Hey'));

        $this->assertSame($handler, $resolvedHandler);
    }

    public function testResolveMessageViaTheirParentClass()
    {
        $handler = function () {};

        $container = new Container();
        $container->set('handler.'.DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->resolve(new ChildDummyMessage('Hey'));

        $this->assertSame($handler, $resolvedHandler);
    }

    public function testLoctesHandlerWithSpecificKeyFormat()
    {
        $handler = function () {};

        $container = new Container();
        $container->set('messages.handler.'.DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container, 'messages.handler.%s');
        $resolvedHandler = $locator->resolve(new DummyMessage('Hey'));

        $this->assertSame($handler, $resolvedHandler);
    }
}
