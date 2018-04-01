<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symphony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;

class AnonymousAuthenticationListenerTest extends TestCase
{
    public function testHandleWithTokenStorageHavingAToken()
    {
        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()))
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', null, $authenticationManager);
        $listener->handle($this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock());
    }

    public function testHandleWithTokenStorageHavingNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $anonymousToken = new AnonymousToken('TheSecret', 'anon.', array());

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->callback(function ($token) {
                return 'TheSecret' === $token->getSecret();
            }))
            ->will($this->returnValue($anonymousToken))
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($anonymousToken)
        ;

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', null, $authenticationManager);
        $listener->handle($this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock());
    }

    public function testHandledEventIsLogged()
    {
        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger->expects($this->once())
            ->method('info')
            ->with('Populated the TokenStorage with an anonymous Token.')
        ;

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', $logger, $authenticationManager);
        $listener->handle($this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock());
    }
}
