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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\HttpFoundation\Request;

class RememberMeListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptySecurityContext()
    {
        list($listener, $context, $service) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')))
        ;

        $context
            ->expects($this->never())
            ->method('setToken')
        ;

        $this->assertNull($listener->handle($this->getGetResponseEvent()));
    }

    public function testOnCoreSecurityDoesNothingWhenNoCookieIsSet()
    {
        list($listener, $context, $service) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->returnValue(null))
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($listener->handle($event));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
    {
        list($listener, $context, $service, $manager) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->returnValue($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')))
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->throwException($exception))
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $listener->handle($event);
    }

    public function testOnCoreSecurity()
    {
        list($listener, $context, $service, $manager) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->returnValue($token))
        ;

        $context
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue($token))
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $listener->handle($event);
    }

    public function testSessionStrategy()
    {
        list($listener, $tokenStorage, $service, $manager) = $this->getListener();

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->returnValue($token))
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue($token))
        ;

        $session = $this->getMock('\Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(true))
        ;
        $session
            ->expects($this->once())
            ->method('migrate')
        ;

        $request = $this->getMock('\Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue(true))
        ;

        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session))
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    protected function getGetResponseEvent()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
    }

    protected function getFilterResponseEvent()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Event\FilterResponseEvent', array(), array(), '', false);
    }

    protected function getListener()
    {
        $listener = new RememberMeListener(
            $context = $this->getContext(),
            $service = $this->getService(),
            $manager = $this->getManager(),
            $logger = $this->getLogger()
        );

        return array($listener, $context, $service, $manager, $logger);
    }

    protected function getLogger()
    {
        return $this->getMock('Psr\Log\LoggerInterface');
    }

    protected function getManager()
    {
        return $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');
    }

    protected function getService()
    {
        return $this->getMock('Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface');
    }

    protected function getContext()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContext')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}
