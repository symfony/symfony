<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\EventListener\IsCsrfTokenValidAttributeListener;
use Symfony\Component\Security\Http\Tests\Fixtures\IsCsrfTokenValidAttributeController;
use Symfony\Component\Security\Http\Tests\Fixtures\IsCsrfTokenValidAttributeMethodsController;

class IsCsrfTokenValidAttributeListenerTest extends TestCase
{
    public function testIsCsrfTokenValidCalledCorrectlyOnInvokableClass()
    {
        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            new IsCsrfTokenValidAttributeController(),
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testNothingHappensWithNoConfig()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectly()
    {
        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withDefaultTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyInPayload()
    {
        $request = new Request(server: ['headers' => ['content-type' => 'application/json']], content: json_encode(['_token' => 'bar']));

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withDefaultTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithCustomExpressionId()
    {
        $request = new Request(query: ['id' => '123'], request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo_123', 'bar'))
            ->willReturn(true);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('"foo_" ~ args.id'), [
                'args' => ['id' => '123'],
                'request' => $request,
            ])
            ->willReturn('foo_123');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withCustomExpressionId'],
            ['123'],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager, $expressionLanguage);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithCustomTokenKey()
    {
        $request = new Request(request: ['my_token_key' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withCustomTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithInvalidTokenKey()
    {
        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', ''))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withInvalidTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testExceptionWhenInvalidToken()
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->withAnyParameters()
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withDefaultTokenKey'],
            [],
            new Request(),
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithDeleteMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('DELETE');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withDeleteMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidIgnoredWithNonMatchingMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('POST');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'));

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withDeleteMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithGetOrPostMethodWithGetMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('GET');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withGetOrPostMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidNoIgnoredWithGetOrPostMethodWithPutMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('PUT');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'));

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withGetOrPostMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithInvalidTokenKeyAndPostMethod()
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('POST');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->withAnyParameters()
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withPostMethodAndInvalidTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidIgnoredWithInvalidTokenKeyAndUnavailableMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('PUT');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid')
            ->withAnyParameters();

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withPostMethodAndInvalidTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }
}
