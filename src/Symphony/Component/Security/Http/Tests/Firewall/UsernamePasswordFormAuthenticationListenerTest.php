<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Tests\Http\Firewall;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symphony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symphony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symphony\Component\Security\Http\HttpUtils;
use Symphony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class UsernamePasswordFormAuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider getUsernameForLength
     */
    public function testHandleWhenUsernameLength($username, $ok)
    {
        $request = Request::create('/login_check', 'POST', array('_username' => $username));
        $request->setSession($this->getMockBuilder('Symphony\Component\HttpFoundation\Session\SessionInterface')->getMock());

        $httpUtils = $this->getMockBuilder('Symphony\Component\Security\Http\HttpUtils')->getMock();
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->will($this->returnValue(true))
        ;

        $failureHandler = $this->getMockBuilder('Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface')->getMock();
        $failureHandler
            ->expects($ok ? $this->never() : $this->once())
            ->method('onAuthenticationFailure')
            ->will($this->returnValue(new Response()))
        ;

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationProviderManager')->disableOriginalConstructor()->getMock();
        $authenticationManager
            ->expects($ok ? $this->once() : $this->never())
            ->method('authenticate')
            ->will($this->returnValue(new Response()))
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock(),
            $authenticationManager,
            $this->getMockBuilder('Symphony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface')->getMock(),
            $httpUtils,
            'TheProviderKey',
            $this->getMockBuilder('Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface')->getMock(),
            $failureHandler,
            array('require_previous_session' => false)
        );

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage The key "_username" must be a string, "array" given.
     */
    public function testHandleNonStringUsername($postOnly)
    {
        $request = Request::create('/login_check', 'POST', array('_username' => array()));
        $request->setSession($this->getMockBuilder('Symphony\Component\HttpFoundation\Session\SessionInterface')->getMock());
        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock(), $httpUtils),
            array('require_previous_session' => false, 'post_only' => $postOnly)
        );
        $event = new GetResponseEvent($this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener->handle($event);
    }

    public function postOnlyDataProvider()
    {
        return array(
            array(true),
            array(false),
        );
    }

    public function getUsernameForLength()
    {
        return array(
            array(str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false),
            array(str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true),
        );
    }
}
