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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\Security\Core\Security;

class UsernamePasswordFormAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getUsernameForLength
     */
    public function testHandleWhenUsernameLength($username, $ok)
    {
        $request = Request::create('/login_check', 'POST', array('_username' => $username));
        $request->setSession($this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface'));

        $httpUtils = $this->getMock('Symfony\Component\Security\Http\HttpUtils');
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->will($this->returnValue(true))
        ;

        $failureHandler = $this->getMock('Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface');
        $failureHandler
            ->expects($ok ? $this->never() : $this->once())
            ->method('onAuthenticationFailure')
            ->will($this->returnValue(new Response()))
        ;

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager')->disableOriginalConstructor()->getMock();
        $authenticationManager
            ->expects($ok ? $this->once() : $this->never())
            ->method('authenticate')
            ->will($this->returnValue(new Response()))
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface'),
            $authenticationManager,
            $this->getMock('Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface'),
            $httpUtils,
            'TheProviderKey',
            $this->getMock('Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface'),
            $failureHandler,
            array('require_previous_session' => false)
        );

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function getUsernameForLength()
    {
        return array(
            array(str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false),
            array(str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true),
        );
    }
}
