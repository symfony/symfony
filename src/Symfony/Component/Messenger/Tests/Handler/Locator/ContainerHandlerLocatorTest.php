<?php

namespace Symfony\Component\Messenger\Tests\Handler\Locator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
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
        $container->set(DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->getHandler(new Envelope(new DummyMessage('Hey')));

        $this->assertSame($handler, $resolvedHandler);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\NoHandlerForMessageException
     * @expectedExceptionMessage No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
     */
    public function testThrowsNoHandlerException()
    {
        $locator = new ContainerHandlerLocator(new Container());
        $locator->getHandler(new Envelope(new DummyMessage('Hey')));
    }

    public function testGetHandlerViaInterface()
    {
        $handler = function () {};

        $container = new Container();
        $container->set(DummyMessageInterface::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->getHandler(new Envelope(new DummyMessage('Hey')));

        $this->assertSame($handler, $resolvedHandler);
    }

    public function testGetHandlerViaParentClass()
    {
        $handler = function () {};

        $container = new Container();
        $container->set(DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->getHandler(new Envelope(new ChildDummyMessage('Hey')));

        $this->assertSame($handler, $resolvedHandler);
    }
}
