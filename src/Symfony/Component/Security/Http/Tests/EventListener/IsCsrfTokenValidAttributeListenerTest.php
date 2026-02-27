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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
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
            $this->createStub(HttpKernelInterface::class),
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
            $this->createStub(HttpKernelInterface::class),
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
            $this->createStub(HttpKernelInterface::class),
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
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['_token' => 'bar']));

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
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
        $controller = new IsCsrfTokenValidAttributeMethodsController();

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
                'this' => $controller,
            ])
            ->willReturn('foo_123');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [$controller, 'withCustomExpressionId'],
            ['123'],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager, $expressionLanguage);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidCalledCorrectlyWithClosureId()
    {
        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo_123', 'bar'))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withCustomExpressionId'],
            ['123'],
            $request,
            null
        );

        $attribute = new IsCsrfTokenValid(static fn (array $args, Request $request, ?object $controller): string => 'foo_'.$args['id']);

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerAttribute(new ControllerAttributeEvent($attribute, $event));
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
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withCustomTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidThrowExceptionWhenInvalidMatchingToken()
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withInvalidTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidThrowExceptionWhenMissingRequestToken()
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
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
            $this->createStub(HttpKernelInterface::class),
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
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
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
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withGetOrPostMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidIgnoredWithGetOrPostMethodWithPutMethod()
    {
        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('PUT');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withGetOrPostMethod'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsCsrfTokenValidThrowExceptionWithInvalidTokenKeyAndPostMethod()
    {
        $this->expectException(InvalidCsrfTokenException::class);

        $request = new Request(request: ['_token' => 'bar']);
        $request->setMethod('POST');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
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
            ->method('isTokenValid');

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), 'withPostMethodAndInvalidTokenKey'],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    #[DataProvider('provideTokenSourceScenarios')]
    public function testIsCsrfTokenValidCalledCorrectlyWithCustomTokenSource(Request $request, string $attributeMethod, string $expectedTokenValue)
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', $expectedTokenValue))
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new IsCsrfTokenValidAttributeMethodsController(), $attributeMethod],
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerArguments($event);
    }

    public static function provideTokenSourceScenarios(): \Generator
    {
        yield 'tokenSource Payload (default)' => [
            new Request(
                request: ['_token' => 'bar_payload'],
                query: ['_token' => 'bar_query']
            ),
            'withDefaultTokenKey',
            'bar_payload',
        ];
        yield 'tokenSource Query' => [
            new Request(
                request: ['_token' => 'bar_payload'],
                query: ['_token' => 'bar_query']
            ),
            'withCustomTokenSourceQuery',
            'bar_query',
        ];
        yield 'tokenSource Query|Payload' => [
            new Request(
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['_token' => 'bar_payload']),
                query: ['_token' => 'bar_query']
            ),
            'withCustomTokenSourceQueryPayload',
            'bar_payload',
        ];
        yield 'tokenSource Header and custom sourceToken' => [
            new Request(
                server: ['HTTP_MY_TOKEN_KEY' => 'bar_header'],
                request: ['my_token_key' => 'bar_payload'],
                query: ['my_token_key' => 'bar_query']
            ),
            'withCustomTokenSourceHeaderAndCustomSourceToken',
            'bar_header',
        ];
    }

    #[RequiresMethod(ControllerAttributeEvent::class, '__construct')]
    public function testonKernelControllerAttributeDelegatesControllerArgumentsEvent()
    {
        $request = new Request(request: ['_token' => 'bar']);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('foo', 'bar'))
            ->willReturn(true);

        $controllerEvent = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            new IsCsrfTokenValidAttributeController(),
            [],
            $request,
            null
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerAttribute(new ControllerAttributeEvent(new IsCsrfTokenValid('foo'), $controllerEvent));
    }

    #[RequiresMethod(ControllerAttributeEvent::class, '__construct')]
    public function testonKernelControllerAttributeIgnoresResponseEvent()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $listener = new IsCsrfTokenValidAttributeListener($csrfTokenManager);
        $listener->onKernelControllerAttribute(new ControllerAttributeEvent(new IsCsrfTokenValid('foo'), $event));
    }
}
