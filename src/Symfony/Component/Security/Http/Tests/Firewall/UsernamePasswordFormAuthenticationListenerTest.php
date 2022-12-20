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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
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
    public function testHandleWhenUsernameLength(string $username, bool $ok)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => $username, '_password' => 'symfony']);
        $request->setSession(self::createMock(SessionInterface::class));

        $httpUtils = self::createMock(HttpUtils::class);
        $httpUtils
            ->expects(self::any())
            ->method('checkRequestPath')
            ->willReturn(true)
        ;
        $httpUtils
            ->method('createRedirectResponse')
            ->willReturn(new RedirectResponse('/hello'))
        ;

        $failureHandler = self::createMock(AuthenticationFailureHandlerInterface::class);
        $failureHandler
            ->expects($ok ? self::never() : self::once())
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $authenticationManager = self::createMock(AuthenticationProviderManager::class);
        $authenticationManager
            ->expects($ok ? self::once() : self::never())
            ->method('authenticate')
            ->willReturnArgument(0)
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            self::createMock(TokenStorageInterface::class),
            $authenticationManager,
            self::createMock(SessionAuthenticationStrategyInterface::class),
            $httpUtils,
            'TheProviderKey',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            $failureHandler,
            ['require_previous_session' => false]
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithArray(bool $postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession(self::createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            self::createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler(self::createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        self::expectException(BadRequestHttpException::class);
        self::expectExceptionMessage('The key "_username" must be a string, "array" given.');

        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithInt(bool $postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession(self::createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            self::createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler(self::createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        self::expectException(BadRequestHttpException::class);
        self::expectExceptionMessage('The key "_username" must be a string, "int" given.');

        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithObject(bool $postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession(self::createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            self::createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler(self::createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        self::expectException(BadRequestHttpException::class);
        self::expectExceptionMessage('The key "_username" must be a string, "stdClass" given.');

        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithToString(bool $postOnly)
    {
        $usernameClass = self::createMock(DummyUserClass::class);
        $usernameClass
            ->expects(self::atLeastOnce())
            ->method('__toString')
            ->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameClass, '_password' => 'symfony']);
        $request->setSession(self::createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            self::createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler(self::createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleWhenPasswordAreNull($postOnly)
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('The key "_password" cannot be null; check that the password field name of the form matches.');

        $request = Request::create('/login_check', 'POST', ['_username' => 'symfony', 'password' => 'symfony']);
        $request->setSession(self::createMock(SessionInterface::class));
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            self::createMock(AuthenticationManagerInterface::class),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler(self::createMock(HttpKernelInterface::class), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider provideInvalidCsrfTokens
     */
    public function testInvalidCsrfToken($invalidToken)
    {
        $formBody = ['_username' => 'fabien', '_password' => 'symfony'];
        if (null !== $invalidToken) {
            $formBody['_csrf_token'] = $invalidToken;
        }

        $request = Request::create('/login_check', 'POST', $formBody);
        $request->setSession(self::createMock(SessionInterface::class));

        $httpUtils = self::createMock(HttpUtils::class);
        $httpUtils
            ->method('checkRequestPath')
            ->willReturn(true)
        ;
        $httpUtils
            ->method('createRedirectResponse')
            ->willReturn(new RedirectResponse('/hello'))
        ;

        $failureHandler = self::createMock(AuthenticationFailureHandlerInterface::class);
        $failureHandler
            ->expects(self::once())
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $authenticationManager = self::createMock(AuthenticationProviderManager::class);
        $authenticationManager
            ->expects(self::never())
            ->method('authenticate')
        ;

        $csrfTokenManager = self::createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $listener = new UsernamePasswordFormAuthenticationListener(
            self::createMock(TokenStorageInterface::class),
            $authenticationManager,
            self::createMock(SessionAuthenticationStrategyInterface::class),
            $httpUtils,
            'TheProviderKey',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            $failureHandler,
            ['require_previous_session' => false],
            null,
            null,
            $csrfTokenManager
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function postOnlyDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function getUsernameForLength(): array
    {
        return [
            [str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false],
            [str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true],
        ];
    }

    public function provideInvalidCsrfTokens(): array
    {
        return [
            ['invalid'],
            [['in' => 'valid']],
            [null],
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
