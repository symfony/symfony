<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\FooController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\RepeatableFooController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ControllerAttributeAtClassAndMethodController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ControllerAttributeAtClassController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ControllerAttributeAtMethodController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\RepeatableControllerAttributeAtClassAndMethodController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\RepeatableControllerAttributeAtClassController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\RepeatableControllerAttributeAtMethodController;

class ControllerAttributeListenerTest extends TestCase
{
    public function testAttributeAtClass()
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new ControllerAttributeAtClassController(), 'foo'],
            $request,
            null
        );

        $listener = new ControllerAttributeListener();
        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertEquals('class', $attributes[FooController::class]->bar);
    }

    public function testAttributeAtMethod()
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new ControllerAttributeAtMethodController(), 'foo'],
            $request,
            null
        );

        $listener = new ControllerAttributeListener();
        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertEquals('method', $attributes[FooController::class]->bar);
    }

    public function testAttributeAtClassAndMethod()
    {
        $listener = new ControllerAttributeListener();

        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new ControllerAttributeAtClassAndMethodController(), 'foo'],
            $request,
            null
        );

        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertEquals('method', $attributes[FooController::class]->bar);

        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new ControllerAttributeAtClassAndMethodController(), 'bar'],
            $request,
            null
        );

        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertEquals('class', $attributes[FooController::class]->bar);
    }

    public function testRepeatableAttributeAtClass()
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new RepeatableControllerAttributeAtClassController(), 'foo'],
            $request,
            null
        );

        $listener = new ControllerAttributeListener();
        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertCount(2, $attributes[RepeatableFooController::class]);
        $this->assertEquals('class1', $attributes[RepeatableFooController::class][0]->bar);
        $this->assertEquals('class2', $attributes[RepeatableFooController::class][1]->bar);
    }

    public function testRepeatableAttributeAtMethod()
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new RepeatableControllerAttributeAtMethodController(), 'foo'],
            $request,
            null
        );

        $listener = new ControllerAttributeListener();
        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertCount(2, $attributes[RepeatableFooController::class]);
        $this->assertEquals('method1', $attributes[RepeatableFooController::class][0]->bar);
        $this->assertEquals('method2', $attributes[RepeatableFooController::class][1]->bar);
    }

    public function testRepeatableAttributeAtClassAndMethod()
    {
        $listener = new ControllerAttributeListener();

        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new RepeatableControllerAttributeAtClassAndMethodController(), 'foo'],
            $request,
            null
        );

        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertCount(4, $attributes[RepeatableFooController::class]);
        $this->assertEquals('class1', $attributes[RepeatableFooController::class][0]->bar);
        $this->assertEquals('class2', $attributes[RepeatableFooController::class][1]->bar);
        $this->assertEquals('method1', $attributes[RepeatableFooController::class][2]->bar);
        $this->assertEquals('method2', $attributes[RepeatableFooController::class][3]->bar);

        $request = new Request();

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new RepeatableControllerAttributeAtClassAndMethodController(), 'bar'],
            $request,
            null
        );

        $listener->onKernelController($event);

        $this->assertNotNull($attributes = $request->attributes->get('_controller_attributes'));
        $this->assertCount(1, $attributes);
        $this->assertCount(2, $attributes[RepeatableFooController::class]);
        $this->assertEquals('class1', $attributes[RepeatableFooController::class][0]->bar);
        $this->assertEquals('class2', $attributes[RepeatableFooController::class][1]->bar);
    }
}
