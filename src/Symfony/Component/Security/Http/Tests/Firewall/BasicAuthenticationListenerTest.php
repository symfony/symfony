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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;

class BasicAuthenticationListenerTest extends TestCase
{
    public function testHandleWithValidUsernameAndPasswordServerParameters()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ));

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
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

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken'))
            ->will($this->returnValue($token))
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock()
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWhenAuthenticationFails()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ));

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $response = new Response();

        $authenticationEntryPoint = $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock();
        $authenticationEntryPoint
            ->expects($this->any())
            ->method('start')
            ->with($this->equalTo($request), $this->isInstanceOf('Symfony\Component\Security\Core\Exception\AuthenticationException'))
            ->will($this->returnValue($response))
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager(array($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface')->getMock())),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->equalTo($response))
        ;

        $listener->handle($event);
    }

    public function testHandleWithNoUsernameServerParameter()
    {
        $request = new Request();

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->never())
            ->method('getToken')
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            'TheProviderKey',
            $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock()
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $request = new Request(array(), array(), array(), array(), array(), array('PHP_AUTH_USER' => 'TheUsername'));

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock()
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
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $providerKey must not be empty
     */
    public function testItRequiresProviderKey()
    {
        new BasicAuthenticationListener(
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock(),
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            '',
            $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock()
        );
    }

    public function testHandleWithADifferentAuthenticatedToken()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ));

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', array('ROLE_FOO'));

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $response = new Response();

        $authenticationEntryPoint = $this->getMockBuilder('Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface')->getMock();
        $authenticationEntryPoint
            ->expects($this->any())
            ->method('start')
            ->with($this->equalTo($request), $this->isInstanceOf('Symfony\Component\Security\Core\Exception\AuthenticationException'))
            ->will($this->returnValue($response))
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager(array($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface')->getMock())),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->equalTo($response))
        ;

        $listener->handle($event);
    }
}
