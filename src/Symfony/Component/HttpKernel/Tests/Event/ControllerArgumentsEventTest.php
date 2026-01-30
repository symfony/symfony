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
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Baz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerArgumentsEventTest extends TestCase
{
    public function testControllerArgumentsEvent(): void
    {
        $event = new ControllerArgumentsEvent(new TestHttpKernel(), static function (): void {}, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $this->assertSame(['test'], $event->getArguments());
    }

    public function testSetAttributes(): void
    {
        $controller = static function (): void {};
        $event = new ControllerArgumentsEvent(new TestHttpKernel(), $controller, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setController($controller, []);

        $this->assertSame([], $event->getAttributes());
    }

    public function testGetAttributes(): void
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
            Baz::class => [
                new Baz(),
            ],
        ];

        $this->assertEquals($expected, $event->getAttributes());

        $attributes = [
            new Bar('class'),
            new Bar('method'),
            new Bar('foo'),
            new Baz(),
        ];
        $event->setController($controller, $attributes);

        $grouped = [
            Bar::class => [
                new Bar('class'),
                new Bar('method'),
                new Bar('foo'),
            ],
            Baz::class => [
                new Baz(),
            ],
        ];
        $this->assertEquals($grouped, $event->getAttributes());
        $this->assertEquals($attributes, $event->getAttributes('*'));
        $this->assertSame($controllerEvent->getAttributes(), $event->getAttributes());
    }

    public function testGetAttributesByClassName(): void
    {
        $controller = new AttributeController();
        $request = new Request();

        $controllerEvent = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $event = new ControllerArgumentsEvent(new TestHttpKernel(), $controllerEvent, ['test'], new Request(), HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            new Bar('class'),
            new Bar('method'),
        ];

        $this->assertEquals($expected, $event->getAttributes(Bar::class));

        // When setting attributes, provide as flat list
        $flatAttributes = [
            new Bar('class'),
            new Bar('method'),
            new Bar('foo'),
        ];
        $event->setController($controller, $flatAttributes);

        $expectedAfterSet = [
            new Bar('class'),
            new Bar('method'),
            new Bar('foo'),
        ];
        $this->assertEquals($expectedAfterSet, $event->getAttributes(Bar::class));
        $this->assertSame($controllerEvent->getAttributes(Bar::class), $event->getAttributes(Bar::class));
    }
}
