<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class DefaultAuthenticationFailureHandlerTest extends TestCase
{
    private Request $request;
    private SessionInterface $session;
    private AuthenticationException $exception;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = Request::create('https://localhost/');
        $this->request->attributes = new ParameterBag(['_stateless' => false]);
        $this->request->setSession($this->session);
        $this->exception = new AuthenticationException();
    }

    public function testForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = Request::create('/');
        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $response = new Response();
        $httpKernel = $this->createStub(HttpKernelInterface::class);
        $httpKernel->method('handle')->willReturn($response);
        $handler = new DefaultAuthenticationFailureHandler($httpKernel, $httpUtils, $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
        $this->assertSame($this->exception, $subRequest->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testRedirect()
    {
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/login'), $result);
    }

    public function testExceptionIsPersistedInSession()
    {
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertTrue($this->session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
        $this->assertSame($this->exception, $this->session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testExceptionIsNotPersistedInSessionOnStatelessRequest()
    {
        $this->request->attributes = new ParameterBag(['_stateless' => true]);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertFalse($this->session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testExceptionIsPassedInRequestOnForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = Request::create('/');

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), $httpUtils, $options, new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($this->exception, $subRequest->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
        $this->assertSame([], $this->session->all());
    }

    public function testRedirectIsLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, redirect triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], $logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testForwardIsLogged()
    {
        $options = ['failure_forward' => true];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, forward triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, $logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwritten()
    {
        $options = ['failure_path' => '/auth/login'];

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/auth/login'), $result);
    }

    public function testFailurePathCanBeOverwrittenWithRequest()
    {
        $this->request->attributes->set('_failure_path', '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/auth/login'), $result);
    }

    public function testFailurePathCanBeOverwrittenWithNestedAttributeInRequest()
    {
        $this->request->attributes->set('_failure_path', ['value' => '/auth/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), ['failure_path_parameter' => '_failure_path[value]'], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/auth/login'), $result);
    }

    public function testFailurePathParameterCanBeOverwritten()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/auth/login'), $result);
    }

    public function testFailurePathFromRequestWithInvalidUrl()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', 'some_route_name');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['Ignoring query parameter "_my_failure_path": not a valid URL.', []],
                    ['Authentication failure, redirect triggered.', ['failure_path' => '/login']],
                ];

                $expectedArgs = array_shift($series);
                $this->assertSame($expectedArgs, $args);
            });

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, $logger);

        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testAbsoluteUrlRedirectionFromRequest()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', 'https://localhost/some-path');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertEquals(new RedirectResponse('https://localhost/some-path'), $result);
    }
}
