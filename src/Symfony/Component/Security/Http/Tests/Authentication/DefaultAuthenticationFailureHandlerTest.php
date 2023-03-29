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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class DefaultAuthenticationFailureHandlerTest extends TestCase
{
    private $httpKernel;
    private $httpUtils;
    private $logger;
    private $request;
    private $response;
    private $session;
    private $exception;

    protected function setUp(): void
    {
        $this->response = new Response();
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
        $this->httpKernel->expects($this->any())
            ->method('handle')->willReturn($this->response);

        $this->httpUtils = $this->createMock(HttpUtils::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->session = $this->createMock(SessionInterface::class);
        $this->request = $this->createMock(Request::class);
        $this->request->expects($this->any())->method('getSession')->willReturn($this->session);
        $this->exception = $this->getMockBuilder(AuthenticationException::class)->onlyMethods(['getMessage'])->getMock();
    }

    public function testForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(SecurityRequestAttributes::AUTHENTICATION_ERROR, $this->exception);
        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($this->response, $result);
    }

    public function testRedirect()
    {
        $response = new RedirectResponse('/login');
        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/login')
            ->willReturn($response);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
    }

    public function testExceptionIsPersistedInSession()
    {
        $this->session->expects($this->once())
            ->method('set')->with(SecurityRequestAttributes::AUTHENTICATION_ERROR, $this->exception);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testExceptionIsPassedInRequestOnForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(SecurityRequestAttributes::AUTHENTICATION_ERROR, $this->exception);

        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $this->session->expects($this->never())->method('set');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testRedirectIsLogged()
    {
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, redirect triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testForwardIsLogged()
    {
        $options = ['failure_forward' => true];

        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($this->getRequest());

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, forward triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwritten()
    {
        $options = ['failure_path' => '/auth/login'];

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwrittenWithRequest()
    {
        $this->request->expects($this->once())
            ->method('get')->with('_failure_path')
            ->willReturn('/auth/login');

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwrittenWithNestedAttributeInRequest()
    {
        $this->request->expects($this->once())
            ->method('get')->with('_failure_path')
            ->willReturn(['value' => '/auth/login']);

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, ['failure_path_parameter' => '_failure_path[value]'], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathParameterCanBeOverwritten()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->expects($this->once())
            ->method('get')->with('_my_failure_path')
            ->willReturn('/auth/login');

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathFromRequestWithInvalidUrl()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->expects($this->once())
            ->method('get')->with('_my_failure_path')
            ->willReturn('some_route_name');

        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['Ignoring query parameter "_my_failure_path": not a valid URL.', []],
                    ['Authentication failure, redirect triggered.', ['failure_path' => '/login']],
                ];

                $expectedArgs = array_shift($series);
                $this->assertSame($expectedArgs, $args);
            });

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);

        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testAbsoluteUrlRedirectionFromRequest()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->expects($this->once())
            ->method('get')->with('_my_failure_path')
            ->willReturn('https://localhost/some-path');

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, 'https://localhost/some-path');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    private function getRequest()
    {
        $request = $this->createMock(Request::class);
        $request->attributes = $this->createMock(ParameterBag::class);

        return $request;
    }
}
