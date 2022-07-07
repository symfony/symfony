<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerArgumentsEventTest extends TestCase
{
    public function testControllerArgumentsEvent()
    {
        $event = new ControllerArgumentsEvent(new TestHttpKernel(), function () {}, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $this->assertEquals($event->getArguments(), ['test']);
    }

    public function testSetAttributes()
    {
        $controller = function () {};
        $event = new ControllerArgumentsEvent(new TestHttpKernel(), $controller, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setController($controller, []);

        $this->assertSame([], $event->getAttributes());
    }

    public function testGetAttributes()
    {
        $controller = new AttributeController();
        $request = new Request();

        $controllerEvent = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $event = new ControllerArgumentsEvent(new TestHttpKernel(), $controllerEvent, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            Bar::class => [
                new Bar('class'),
                new Bar('method'),
            ],
        ];

        $this->assertEquals($expected, $event->getAttributes());

        $expected[Bar::class][] = new Bar('foo');
        $event->setController($controller, $expected);

        $this->assertEquals($expected, $event->getAttributes());
        $this->assertSame($controllerEvent->getAttributes(), $event->getAttributes());
    }
}
