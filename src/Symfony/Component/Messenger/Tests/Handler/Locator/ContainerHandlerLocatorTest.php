<?php

namespace Symfony\Component\Messenger\Tests\Handler\Locator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Handler\Locator\ContainerHandlerLocator;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class ContainerHandlerLocatorTest extends TestCase
{
    public function testItLocatesHandlerUsingTheMessageClass()
    {
        $handler = function () {};

        $container = new Container();
        $container->set(DummyMessage::class, $handler);

        $locator = new ContainerHandlerLocator($container);
        $resolvedHandler = $locator->getHandler(DummyMessage::class);

        $this->assertSame($handler, $resolvedHandler);
    }

    public function testNoHandlersReturnsNull()
    {
        $locator = new ContainerHandlerLocator(new Container());
        $this->assertNull($locator->getHandler(DummyMessage::class));
    }

    public function testGetHandlerViaTopic()
    {
        $handler1 = function () {};
        $handler2 = function () {};

        $container = new Container();
        $locator = new ContainerHandlerLocator($container);
        $container->set(DummyMessage::class, $handler1);
        $container->set('foo', $handler2);

        $resolvedHandler = $locator->getHandler('foo');
        $this->assertSame($handler2, $resolvedHandler);
    }
}
