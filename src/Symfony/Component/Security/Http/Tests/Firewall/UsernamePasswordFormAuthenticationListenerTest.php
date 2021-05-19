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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * @group legacy
 */
class UsernamePasswordFormAuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider getUsernameForLength
     */
    public function testHandleWhenUsernameLength($username, $ok)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => $username, '_password' => 'symfony']);
        $request->setSession($this->createMock(SessionInterface::class));

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->willReturn(true)
        ;
        $httpUtils
            ->method('createRedirectResponse')
            ->willReturn(new RedirectResponse('/hello'))
        ;

        $failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);
        $failureHandler
            ->expects($ok ? $this->never() : $this->once())
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $authenticationManager = $this->createMock(AuthenticationProviderManager::class);
        $authenticationManager
            ->expects($ok ? $this->once() : $this->never())
            ->method('authenticate')
            ->willReturnArgument(0)
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            $this->createMock(TokenStorageInterface::class),
            $authenticationManager,
            $this->createMock(SessionAuthenticationStrategyInterface::class),
            $httpUtils,
            'TheProviderKey',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            $failureHandler,
            ['require_previous_session' => false]
        );

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithArray($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession($this->createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithInt($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "int" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession($this->createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithObject($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "stdClass" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession($this->createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWith__toString($postOnly)
    {
        $usernameClass = $this->createMock(DummyUserClass::class);
        $usernameClass
            ->expects($this->atLeastOnce())
            ->method('__toString')
            ->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameClass, '_password' => 'symfony']);
        $request->setSession($this->createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleWhenPasswordAreNull($postOnly)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The key "_password" cannot be null; check that the password field name of the form matches.');

        $request = Request::create('/login_check', 'POST', ['_username' => 'symfony', 'password' => 'symfony']);
        $request->setSession($this->createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    public function postOnlyDataProvider()
    {
        return [
            [true],
            [false],
        ];
    }

    public function getUsernameForLength()
    {
        return [
            [str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false],
            [str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true],
        ];
    }
}

class DummyUserClass
{
    public function __toString(): string
    {
        return '';
    }
}
