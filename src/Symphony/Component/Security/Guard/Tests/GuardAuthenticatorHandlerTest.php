<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Guard\AuthenticatorInterface;
use Symphony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symphony\Component\Security\Http\SecurityEvents;

class GuardAuthenticatorHandlerTest extends TestCase
{
    private $tokenStorage;
    private $dispatcher;
    private $token;
    private $request;
    private $guardAuthenticator;

    public function testAuthenticateWithToken()
    {
        $this->tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->token);

        $loginEvent = new InteractiveLoginEvent($this->request, $this->token);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(SecurityEvents::INTERACTIVE_LOGIN), $this->equalTo($loginEvent))
        ;

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testHandleAuthenticationSuccess()
    {
        $providerKey = 'my_handleable_firewall';
        $response = new Response('Guard all the things!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token, $providerKey)
            ->will($this->returnValue($response));

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationSuccess($this->token, $this->request, $this->guardAuthenticator, $providerKey);
        $this->assertSame($response, $actualResponse);
    }

    public function testHandleAuthenticationFailure()
    {
        // setToken() not called - getToken() will return null, so there's nothing to clear
        $this->tokenStorage->expects($this->never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->will($this->returnValue($response));

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, 'firewall_provider_key');
        $this->assertSame($response, $actualResponse);
    }

    /**
     * @dataProvider getTokenClearingTests
     */
    public function testHandleAuthenticationClearsToken($tokenClass, $tokenProviderKey, $actualProviderKey, $shouldTokenBeCleared)
    {
        $token = $this->getMockBuilder($tokenClass)
            ->disableOriginalConstructor()
            ->getMock();
        $token->expects($this->any())
            ->method('getProviderKey')
            ->will($this->returnValue($tokenProviderKey));

        // make the $token be the current token
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->tokenStorage->expects($shouldTokenBeCleared ? $this->once() : $this->never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->will($this->returnValue($response));

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, $actualProviderKey);
        $this->assertSame($response, $actualResponse);
    }

    public function getTokenClearingTests()
    {
        $tests = array();
        // correct token class and matching firewall => clear the token
        $tests[] = array('Symphony\Component\Security\Guard\Token\PostAuthenticationGuardToken', 'the_firewall_key', 'the_firewall_key', true);
        $tests[] = array('Symphony\Component\Security\Guard\Token\PostAuthenticationGuardToken', 'the_firewall_key', 'different_key', false);
        $tests[] = array('Symphony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', 'the_firewall_key', 'the_firewall_key', false);

        return $tests;
    }

    protected function setUp()
    {
        $this->tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->dispatcher = $this->getMockBuilder('Symphony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $this->request = new Request(array(), array(), array(), array(), array(), array());
        $this->guardAuthenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
    }

    protected function tearDown()
    {
        $this->tokenStorage = null;
        $this->dispatcher = null;
        $this->token = null;
        $this->request = null;
        $this->guardAuthenticator = null;
    }
}
