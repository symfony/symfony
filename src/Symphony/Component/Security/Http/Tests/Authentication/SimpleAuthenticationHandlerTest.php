<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symphony\Component\Security\Http\Authentication\SimpleAuthenticationHandler;

class SimpleAuthenticationHandlerTest extends TestCase
{
    private $successHandler;

    private $failureHandler;

    private $request;

    private $token;

    private $authenticationException;

    private $response;

    protected function setUp()
    {
        $this->successHandler = $this->getMockBuilder('Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface')->getMock();
        $this->failureHandler = $this->getMockBuilder('Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface')->getMock();

        $this->request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
        $this->token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        // No methods are invoked on the exception; we just assert on its class
        $this->authenticationException = new AuthenticationException();

        $this->response = new Response();
    }

    public function testOnAuthenticationSuccessFallsBackToDefaultHandlerIfSimpleIsNotASuccessHandler()
    {
        $authenticator = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface')->getMock();

        $this->successHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token)
            ->will($this->returnValue($this->response));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationSuccess($this->request, $this->token);

        $this->assertSame($this->response, $result);
    }

    public function testOnAuthenticationSuccessCallsSimpleAuthenticator()
    {
        $this->successHandler->expects($this->never())
            ->method('onAuthenticationSuccess');

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestSuccessHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token)
            ->will($this->returnValue($this->response));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationSuccess($this->request, $this->token);

        $this->assertSame($this->response, $result);
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage onAuthenticationSuccess method must return null to use the default success handler, or a Response object
     */
    public function testOnAuthenticationSuccessThrowsAnExceptionIfNonResponseIsReturned()
    {
        $this->successHandler->expects($this->never())
            ->method('onAuthenticationSuccess');

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestSuccessHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token)
            ->will($this->returnValue(new \stdClass()));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $handler->onAuthenticationSuccess($this->request, $this->token);
    }

    public function testOnAuthenticationSuccessFallsBackToDefaultHandlerIfNullIsReturned()
    {
        $this->successHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token)
            ->will($this->returnValue($this->response));

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestSuccessHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token)
            ->will($this->returnValue(null));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationSuccess($this->request, $this->token);

        $this->assertSame($this->response, $result);
    }

    public function testOnAuthenticationFailureFallsBackToDefaultHandlerIfSimpleIsNotAFailureHandler()
    {
        $authenticator = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface')->getMock();

        $this->failureHandler->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->authenticationException)
            ->will($this->returnValue($this->response));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationFailure($this->request, $this->authenticationException);

        $this->assertSame($this->response, $result);
    }

    public function testOnAuthenticationFailureCallsSimpleAuthenticator()
    {
        $this->failureHandler->expects($this->never())
            ->method('onAuthenticationFailure');

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestFailureHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->authenticationException)
            ->will($this->returnValue($this->response));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationFailure($this->request, $this->authenticationException);

        $this->assertSame($this->response, $result);
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage onAuthenticationFailure method must return null to use the default failure handler, or a Response object
     */
    public function testOnAuthenticationFailureThrowsAnExceptionIfNonResponseIsReturned()
    {
        $this->failureHandler->expects($this->never())
            ->method('onAuthenticationFailure');

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestFailureHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->authenticationException)
            ->will($this->returnValue(new \stdClass()));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $handler->onAuthenticationFailure($this->request, $this->authenticationException);
    }

    public function testOnAuthenticationFailureFallsBackToDefaultHandlerIfNullIsReturned()
    {
        $this->failureHandler->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->authenticationException)
            ->will($this->returnValue($this->response));

        $authenticator = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Tests\TestFailureHandlerInterface');
        $authenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $this->authenticationException)
            ->will($this->returnValue(null));

        $handler = new SimpleAuthenticationHandler($authenticator, $this->successHandler, $this->failureHandler);
        $result = $handler->onAuthenticationFailure($this->request, $this->authenticationException);

        $this->assertSame($this->response, $result);
    }
}

interface TestSuccessHandlerInterface extends AuthenticationSuccessHandlerInterface, SimpleAuthenticatorInterface
{
}

interface TestFailureHandlerInterface extends AuthenticationFailureHandlerInterface, SimpleAuthenticatorInterface
{
}
