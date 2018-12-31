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
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AbstractPreAuthenticatedListenerTest extends TestCase
{
    public function testHandleWithValidValues()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($token)
        ;

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->willReturn($token)
        ;

        $listener = $this->getMockForAbstractClass('Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWhenAuthenticationFails()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->willThrowException($exception)
        ;

        $listener = $this->getMockForAbstractClass('Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWhenAuthenticationFailsWithDifferentToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', array('ROLE_FOO'));

        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->willThrowException($exception)
        ;

        $listener = $this->getMockForAbstractClass('Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $listener = $this->getMockForAbstractClass('Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWithAnInvalidSimilarToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = new PreAuthenticatedToken('AnotherUser', 'TheCredentials', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with(null)
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->willThrowException($exception)
        ;

        $listener = $this->getMockForAbstractClass('Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }
}
