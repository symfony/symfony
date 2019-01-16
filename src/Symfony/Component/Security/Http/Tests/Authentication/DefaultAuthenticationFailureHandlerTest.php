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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;

class DefaultAuthenticationFailureHandlerTest extends TestCase
{
    private $httpKernel;
    private $httpUtils;
    private $logger;
    private $request;
    private $session;
    private $exception;

    protected function setUp()
    {
        $this->httpKernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $this->httpUtils = $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')->getMock();
        $this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

        $this->session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $this->request->expects($this->any())->method('getSession')->will($this->returnValue($this->session));
        $this->exception = $this->getMockBuilder('Symfony\Component\Security\Core\Exception\AuthenticationException')->setMethods(['getMessage'])->getMock();
    }

    public function testForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);
        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->will($this->returnValue($subRequest));

        $response = new Response();
        $this->httpKernel->expects($this->once())
            ->method('handle')->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->will($this->returnValue($response));

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
    }

    public function testRedirect()
    {
        $response = new Response();
        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/login')
            ->will($this->returnValue($response));

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
    }

    public function testExceptionIsPersistedInSession()
    {
        $this->session->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testExceptionIsPassedInRequestOnForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);

        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->will($this->returnValue($subRequest));

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
            ->will($this->returnValue($this->getRequest()));

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
            ->will($this->returnValue('/auth/login'));

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwrittenWithNestedAttributeInRequest()
    {
        $this->request->expects($this->once())
            ->method('get')->with('_failure_path')
            ->will($this->returnValue(['value' => '/auth/login']));

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
            ->will($this->returnValue('/auth/login'));

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    private function getRequest()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $request->attributes = $this->getMockBuilder('Symfony\Component\HttpFoundation\ParameterBag')->getMock();

        return $request;
    }
}
