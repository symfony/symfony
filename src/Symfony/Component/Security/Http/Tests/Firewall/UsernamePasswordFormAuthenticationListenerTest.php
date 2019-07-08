<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class UsernamePasswordFormAuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider getUsernameForLength
     */
    public function testHandleWhenUsernameLength($username, $ok)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => $username]);
        $request->setSession($this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());

        $httpUtils = $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')->getMock();
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->willReturn(true)
        ;

        $failureHandler = $this->getMockBuilder('Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface')->getMock();
        $failureHandler
            ->expects($ok ? $this->never() : $this->once())
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager')->disableOriginalConstructor()->getMock();
        $authenticationManager
            ->expects($ok ? $this->once() : $this->never())
            ->method('authenticate')
            ->willReturn(new Response())
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock(),
            $authenticationManager,
            $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface')->getMock(),
            $httpUtils,
            'TheProviderKey',
            $this->getMockBuilder('Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface')->getMock(),
            $failureHandler,
            ['require_previous_session' => false]
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "_username" must be a string, "array" given.
     */
    public function testHandleNonStringUsernameWithArray($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession($this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "_username" must be a string, "integer" given.
     */
    public function testHandleNonStringUsernameWithInt($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession($this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "_username" must be a string, "object" given.
     */
    public function testHandleNonStringUsernameWithObject($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession($this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWith__toString($postOnly)
    {
        $usernameClass = $this->getMockBuilder(DummyUserClass::class)->getMock();
        $usernameClass
            ->expects($this->atLeastOnce())
            ->method('__toString')
            ->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameClass]);
        $request->setSession($this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new RequestEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
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
    public function __toString()
    {
        return '';
    }
}
