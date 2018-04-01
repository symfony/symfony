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
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symphony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symphony\Component\Security\Core\Exception\AuthenticationException;

class AbstractPreAuthenticatedListenerTest extends TestCase
{
    public function testHandleWithValidValues()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->will($this->returnValue($token))
        ;

        $listener = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->will($this->returnValue($userCredentials));

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWhenAuthenticationFails()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->will($this->throwException($exception))
        ;

        $listener = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->will($this->returnValue($userCredentials));

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWhenAuthenticationFailsWithDifferentToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', array('ROLE_FOO'));

        $request = new Request(array(), array(), array(), array(), array(), array());

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->will($this->throwException($exception))
        ;

        $listener = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->will($this->returnValue($userCredentials));

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $listener = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->will($this->returnValue($userCredentials));

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWithAnInvalidSimilarToken()
    {
        $userCredentials = array('TheUser', 'TheCredentials');

        $request = new Request(array(), array(), array(), array(), array(), array());

        $token = new PreAuthenticatedToken('AnotherUser', 'TheCredentials', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo(null))
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken'))
            ->will($this->throwException($exception))
        ;

        $listener = $this->getMockForAbstractClass('Symphony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener', array(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ));
        $listener
            ->expects($this->once())
            ->method('getPreAuthenticatedData')
            ->will($this->returnValue($userCredentials));

        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }
}
