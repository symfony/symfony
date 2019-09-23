<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListenerTest extends TestCase
{
    /**
     * @var UsernamePasswordJsonAuthenticationListener
     */
    private $listener;

    private function createListener(array $options = [], $success = true, $matchCheckPath = true)
    {
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $httpUtils = $this->getMockBuilder(HttpUtils::class)->getMock();
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->willReturn($matchCheckPath)
        ;
        $authenticationManager = $this->getMockBuilder(AuthenticationManagerInterface::class)->getMock();

        $authenticatedToken = $this->getMockBuilder(TokenInterface::class)->getMock();

        if ($success) {
            $authenticationManager->method('authenticate')->willReturn($authenticatedToken);
        } else {
            $authenticationManager->method('authenticate')->willThrowException(new AuthenticationException());
        }

        $authenticationSuccessHandler = $this->getMockBuilder(AuthenticationSuccessHandlerInterface::class)->getMock();
        $authenticationSuccessHandler->method('onAuthenticationSuccess')->willReturn(new Response('ok'));
        $authenticationFailureHandler = $this->getMockBuilder(AuthenticationFailureHandlerInterface::class)->getMock();
        $authenticationFailureHandler->method('onAuthenticationFailure')->willReturn(new Response('ko'));

        $this->listener = new UsernamePasswordJsonAuthenticationListener($tokenStorage, $authenticationManager, $httpUtils, 'providerKey', $authenticationSuccessHandler, $authenticationFailureHandler, $options);
    }

    public function testHandleSuccessIfRequestContentTypeIsJson()
    {
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testSuccessIfRequestFormatIsJsonLD()
    {
        $this->createListener();
        $request = new Request([], [], [], [], [], [], '{"username": "dunglas", "password": "foo"}');
        $request->setRequestFormat('json-ld');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testHandleFailure()
    {
        $this->createListener([], false);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ko', $event->getResponse()->getContent());
    }

    public function testUsePath()
    {
        $this->createListener(['username_path' => 'user.login', 'password_path' => 'user.pwd']);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "pwd": "foo"}}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testAttemptAuthenticationNoJson()
    {
        $this->expectException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $this->expectExceptionMessage('Invalid JSON');
        $this->createListener();
        $request = new Request();
        $request->setRequestFormat('json');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationNoUsername()
    {
        $this->expectException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $this->expectExceptionMessage('The key "username" must be provided');
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"usr": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationNoPassword()
    {
        $this->expectException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $this->expectExceptionMessage('The key "password" must be provided');
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "pass": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationUsernameNotAString()
    {
        $this->expectException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $this->expectExceptionMessage('The key "username" must be a string.');
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": 1, "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationPasswordNotAString()
    {
        $this->expectException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $this->expectExceptionMessage('The key "password" must be a string.');
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": 1}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
    }

    public function testAttemptAuthenticationUsernameTooLong()
    {
        $this->createListener();
        $username = str_repeat('x', Security::MAX_USERNAME_LENGTH + 1);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], sprintf('{"username": "%s", "password": 1}', $username));
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertSame('ko', $event->getResponse()->getContent());
    }

    public function testDoesNotAttemptAuthenticationIfRequestPathDoesNotMatchCheckPath()
    {
        $this->createListener(['check_path' => '/'], true, false);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json']);
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);
        $event->setResponse(new Response('original'));

        $this->listener->handle($event);
        $this->assertSame('original', $event->getResponse()->getContent());
    }

    public function testDoesNotAttemptAuthenticationIfRequestContentTypeIsNotJson()
    {
        $this->createListener();
        $request = new Request([], [], [], [], [], [], '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);
        $event->setResponse(new Response('original'));

        $this->listener->handle($event);
        $this->assertSame('original', $event->getResponse()->getContent());
    }

    public function testAttemptAuthenticationIfRequestPathMatchesCheckPath()
    {
        $this->createListener(['check_path' => '/']);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertSame('ok', $event->getResponse()->getContent());
    }

    public function testNoErrorOnMissingSessionStrategy()
    {
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $this->configurePreviousSession($request);
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    public function testMigratesViaSessionStrategy()
    {
        $this->createListener();
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $this->configurePreviousSession($request);
        $event = new GetResponseEvent($this->getMockBuilder(KernelInterface::class)->getMock(), $request, KernelInterface::MASTER_REQUEST);

        $sessionStrategy = $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface')->getMock();
        $sessionStrategy->expects($this->once())
            ->method('onAuthentication')
            ->with($request, $this->isInstanceOf(TokenInterface::class));
        $this->listener->setSessionAuthenticationStrategy($sessionStrategy);

        $this->listener->handle($event);
        $this->assertEquals('ok', $event->getResponse()->getContent());
    }

    private function configurePreviousSession(Request $request)
    {
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
        $session->expects($this->any())
            ->method('getName')
            ->willReturn('test_session_name');
        $request->setSession($session);
        $request->cookies->set('test_session_name', 'session_cookie_val');
    }
}
