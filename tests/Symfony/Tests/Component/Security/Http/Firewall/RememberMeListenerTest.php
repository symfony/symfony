<?php

namespace Symfony\Tests\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\HttpFoundation\Request;

class RememberMeListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testCheckCookiesDoesNotTryToPopulateNonEmptySecurityContext()
    {
        list($listener, $context, $service,,) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')))
        ;

        $context
            ->expects($this->never())
            ->method('setToken')
        ;

        $this->assertNull($listener->handle($this->getEvent()));
    }

    public function testCheckCookiesDoesNothingWhenNoCookieIsSet()
    {
        list($listener, $context, $service,,) = $this->getListener();

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

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($listener->handle($event));
    }

    public function testCheckCookiesIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
    {
        list($listener, $context, $service, $manager,) = $this->getListener();

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

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        $listener->handle($event);
    }

    public function testCheckCookies()
    {
        list($listener, $context, $service, $manager,) = $this->getListener();

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

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        $listener->handle($event);
    }

    protected function getEvent()
    {
        return $this->getMock('Symfony\Component\EventDispatcher\Event', array(), array(), '', false);
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
        return $this->getMock('Symfony\Component\HttpKernel\Log\LoggerInterface');
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