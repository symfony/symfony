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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerAttributeEventTest extends TestCase
{
    public function testEvaluateReturnsValueForNonExpressionOrClosure()
    {
        $controllerEvent = new ControllerEvent(new TestHttpKernel(), static function () {}, new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event = new ControllerAttributeEvent(new \stdClass(), $controllerEvent);

        $this->assertSame('value', $event->evaluate('value'));
    }

    public function testEvaluateDelegatesToControllerEvent()
    {
        $request = new Request();
        $controller = [new AttributeController(), 'action'];
        $controllerEvent = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('request'), [
                'request' => $request,
                'args' => [],
                'this' => $controller[0],
            ])
            ->willReturn($request);

        $event = new ControllerAttributeEvent(new \stdClass(), $controllerEvent, $expressionLanguage);

        $this->assertSame($request, $event->evaluate(new Expression('request')));
    }

    public function testEvaluateDelegatesToControllerArgumentsEvent()
    {
        $request = new Request();
        $controller = [new AttributeController(), 'action'];
        $controllerEvent = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $argumentsEvent = new ControllerArgumentsEvent(new TestHttpKernel(), $controllerEvent, ['value'], $request, HttpKernelInterface::MAIN_REQUEST);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('args["baz"]'), [
                'request' => $request,
                'args' => ['baz' => 'value'],
                'this' => $controller[0],
            ])
            ->willReturn('value');

        $event = new ControllerAttributeEvent(new \stdClass(), $argumentsEvent, $expressionLanguage);

        $this->assertSame('value', $event->evaluate(new Expression('args["baz"]')));
    }

    public function testEvaluateDelegatesToControllerMetadata()
    {
        $request = new Request();
        $controller = [new AttributeController(), 'action'];
        $kernel = new TestHttpKernel();
        $controllerEvent = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $argumentsEvent = new ControllerArgumentsEvent($kernel, $controllerEvent, ['value'], $request, HttpKernelInterface::MAIN_REQUEST);
        $metadata = new ControllerArgumentsMetadata($controllerEvent, $argumentsEvent);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response(), $metadata);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('args["baz"]'), [
                'request' => $request,
                'args' => ['baz' => 'value'],
                'this' => $controller[0],
            ])
            ->willReturn('value');

        $event = new ControllerAttributeEvent(new \stdClass(), $responseEvent, $expressionLanguage);

        $this->assertSame('value', $event->evaluate(new Expression('args["baz"]')));
    }
}
