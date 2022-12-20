<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @group legacy
 */
class GuardAuthenticatorHandlerTest extends TestCase
{
    private $tokenStorage;
    private $dispatcher;
    private $token;
    private $request;
    private $sessionStrategy;
    private $guardAuthenticator;

    public function testAuthenticateWithToken()
    {
        $this->tokenStorage->expects(self::once())
            ->method('setToken')
            ->with($this->token);

        $loginEvent = new InteractiveLoginEvent($this->request, $this->token);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::equalTo($loginEvent), self::equalTo(SecurityEvents::INTERACTIVE_LOGIN))
        ;

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testHandleAuthenticationSuccess()
    {
        $providerKey = 'my_handleable_firewall';
        $response = new Response('Guard all the things!');
        $this->guardAuthenticator->expects(self::once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token, $providerKey)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationSuccess($this->token, $this->request, $this->guardAuthenticator, $providerKey);
        self::assertSame($response, $actualResponse);
    }

    public function testHandleAuthenticationFailure()
    {
        // setToken() not called - getToken() will return null, so there's nothing to clear
        $this->tokenStorage->expects(self::never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects(self::once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, 'firewall_provider_key');
        self::assertSame($response, $actualResponse);
    }

    /**
     * @dataProvider getTokenClearingTests
     */
    public function testHandleAuthenticationClearsToken($tokenProviderKey, $actualProviderKey)
    {
        $this->tokenStorage->expects(self::never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects(self::once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, $actualProviderKey);
        self::assertSame($response, $actualResponse);
    }

    public function getTokenClearingTests()
    {
        $tests = [];
        // matching firewall => clear the token
        $tests[] = ['the_firewall_key', 'the_firewall_key'];
        $tests[] = ['the_firewall_key', 'different_key'];
        $tests[] = ['the_firewall_key', 'the_firewall_key'];

        return $tests;
    }

    public function testNoFailureIfSessionStrategyNotPassed()
    {
        $this->configurePreviousSession();

        $this->tokenStorage->expects(self::once())
            ->method('setToken')
            ->with($this->token);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testSessionStrategyIsCalled()
    {
        $this->configurePreviousSession();

        $this->sessionStrategy->expects(self::once())
            ->method('onAuthentication')
            ->with($this->request, $this->token);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testSessionStrategyIsNotCalledWhenStateless()
    {
        $this->configurePreviousSession();

        $this->sessionStrategy->expects(self::never())
            ->method('onAuthentication');

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher, ['some_provider_key']);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request, 'some_provider_key');
    }

    public function testSessionIsNotInstantiatedOnStatelessFirewall()
    {
        $sessionFactory = self::getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $sessionFactory->expects(self::never())
            ->method('__invoke');

        $this->request->setSessionFactory($sessionFactory);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher, ['stateless_provider_key']);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request, 'stateless_provider_key');
    }

    protected function setUp(): void
    {
        $this->tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->dispatcher = self::createMock(EventDispatcherInterface::class);
        $this->token = self::createMock(TokenInterface::class);
        $this->request = new Request([], [], [], [], [], []);
        $this->sessionStrategy = self::createMock(SessionAuthenticationStrategyInterface::class);
        $this->guardAuthenticator = self::createMock(AuthenticatorInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tokenStorage = null;
        $this->dispatcher = null;
        $this->token = null;
        $this->request = null;
        $this->guardAuthenticator = null;
    }

    private function configurePreviousSession()
    {
        $session = self::createMock(SessionInterface::class);
        $session->expects(self::any())
            ->method('getName')
            ->willReturn('test_session_name');
        $this->request->setSession($session);
        $this->request->cookies->set('test_session_name', 'session_cookie_val');
    }
}
