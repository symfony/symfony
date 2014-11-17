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

use Symfony\Component\Security\Http\Firewall\ExpiredSessionListener;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class ExpiredSessionListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleWhenNoSession()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(false));

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new ExpiredSessionListener(
            $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface'),
            $this->getHttpUtils(),
            $this->getSessionRegistry()
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenNoToken()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(true));
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $securityContext = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null));

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new ExpiredSessionListener(
            $securityContext,
            $this->getHttpUtils(),
            $this->getSessionRegistry()
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenSessionInformationIsExpired()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('foo'));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(true));
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token
            ->expects($this->any())
            ->method('getUsername')
            ->will($this->returnValue('foobar'));

        $securityContext = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $sessionInformation = $this->getSessionInformation();
        $sessionInformation
            ->expects($this->once())
            ->method('isExpired')
            ->will($this->returnValue(true));

        $sessionRegistry = $this->getSessionRegistry();
        $sessionRegistry
            ->expects($this->once())
            ->method('getSessionInformation')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($sessionInformation));

        $response = $this->getMock('Symfony\Component\HttpFoundation\Response');

        $httpUtils = $this->getHttpUtils();
        $httpUtils
            ->expects($this->once())
            ->method('createRedirectResponse')
            ->with($this->identicalTo($request), $this->equalTo('/'))
            ->will($this->returnValue($response));

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->identicalTo($response));

        $listener = new ExpiredSessionListener(
            $securityContext,
            $httpUtils,
            $sessionRegistry
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenSessionInformationIsNotExpired()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('foo'));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(true));
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token
            ->expects($this->any())
            ->method('getUsername')
            ->will($this->returnValue('foobar'));

        $securityContext = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $sessionInformation = $this->getSessionInformation();
        $sessionInformation
            ->expects($this->once())
            ->method('isExpired')
            ->will($this->returnValue(false));

        $sessionRegistry = $this->getSessionRegistry();
        $sessionRegistry
            ->expects($this->once())
            ->method('getSessionInformation')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($sessionInformation));
        $sessionRegistry
            ->expects($this->once())
            ->method('refreshLastRequest')
            ->with($this->equalTo('foo'));

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new ExpiredSessionListener(
            $securityContext,
            $this->getHttpUtils(),
            $sessionRegistry
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenNoSessionInformationIsRegistered()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('foo'));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(true));
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUsername')
            ->will($this->returnValue('foobar'));

        $securityContext = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $securityContext
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token));

        $sessionRegistry = $this->getSessionRegistry();
        $sessionRegistry
            ->expects($this->once())
            ->method('getSessionInformation')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue(null));
        $sessionRegistry
            ->expects($this->once())
            ->method('registerNewSession')
            ->with($this->equalTo('foo'), $this->equalTo('foobar'));

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new ExpiredSessionListener(
            $securityContext,
            $this->getHttpUtils(),
            $sessionRegistry
        );

        $this->assertNull($listener->handle($event));
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getSessionRegistry()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionRegistry')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getSessionInformation()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionInformation')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
