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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;

class BasicAuthenticationListenerTest extends TestCase
{
    public function testHandleWithValidUsernameAndPasswordServerParameters()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $authenticationManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock();
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf(UsernamePasswordToken::class))
            ->willReturn($token)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener($event);
    }

    public function testHandleWhenAuthenticationFails()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $response = new Response();

        $authenticationEntryPoint = $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock();
        $authenticationEntryPoint
            ->expects($this->any())
            ->method('start')
            ->with($this->equalTo($request), $this->isInstanceOf(\Symfony\Component\Security\Core\Exception\AuthenticationException::class))
            ->willReturn($response)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager([$this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock()]),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->equalTo($response))
        ;

        $listener($event);
    }

    public function testHandleWithNoUsernameServerParameter()
    {
        $request = new Request();

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->never())
            ->method('getToken')
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            'TheProviderKey',
            $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener($event);
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $request = new Request([], [], [], [], [], ['PHP_AUTH_USER' => 'TheUsername']);

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', ['ROLE_FOO']);

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $authenticationManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock();
        $authenticationManager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener($event);
    }

    public function testItRequiresProviderKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$providerKey must not be empty');
        new BasicAuthenticationListener(
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            '',
            $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock()
        );
    }

    public function testHandleWithADifferentAuthenticatedToken()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', ['ROLE_FOO']);

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;
        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $response = new Response();

        $authenticationEntryPoint = $this->getMockBuilder(\Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface::class)->getMock();
        $authenticationEntryPoint
            ->expects($this->any())
            ->method('start')
            ->with($this->equalTo($request), $this->isInstanceOf(\Symfony\Component\Security\Core\Exception\AuthenticationException::class))
            ->willReturn($response)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager([$this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock()]),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->equalTo($response))
        ;

        $listener($event);
    }
}
