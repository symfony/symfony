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
use Symfony\Component\Security\Http\Firewall\AccessListener;

class AccessListenerTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testHandleWhenTheAccessDecisionManagerDecidesToRefuseAccess()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(array('foo' => 'bar'), null)))
        ;

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token
            ->expects($this->any())
            ->method('isAuthenticated')
            ->will($this->returnValue(true))
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($token), $this->equalTo(array('foo' => 'bar')), $this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWhenTheTokenIsNotAuthenticated()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(array('foo' => 'bar'), null)))
        ;

        $notAuthenticatedToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $notAuthenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->will($this->returnValue(false))
        ;

        $authenticatedToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $authenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->will($this->returnValue(true))
        ;

        $authManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($notAuthenticatedToken))
            ->will($this->returnValue($authenticatedToken))
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($notAuthenticatedToken))
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($authenticatedToken))
        ;

        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($authenticatedToken), $this->equalTo(array('foo' => 'bar')), $this->equalTo($request))
            ->will($this->returnValue(true))
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $authManager
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWhenThereIsNoAccessMapEntryMatchingTheRequest()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(null, null)))
        ;

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token
            ->expects($this->never())
            ->method('isAuthenticated')
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock(),
            $accessMap,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException
     */
    public function testHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock(),
            $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();

        $listener->handle($event);
    }
}
