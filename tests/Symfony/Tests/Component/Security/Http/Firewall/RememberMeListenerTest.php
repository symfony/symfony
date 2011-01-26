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
    public function testRegister()
    {
        list($listener,,,,) = $this->getListener();

        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcher');
        $dispatcher
            ->expects($this->at(0))
            ->method('connect')
            ->with($this->equalTo('core.security'))
        ;
        $dispatcher
            ->expects($this->at(1))
            ->method('connect')
            ->with($this->equalTo('core.response'))
        ;

        $listener->register($dispatcher);
    }

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

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($this->getEvent()));
        $this->assertNull($this->getLastState($listener));
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

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($event));
        $this->assertNull($this->getLastState($listener));
    }

    public function testCheckCookiesIgnoresAuthenticationExceptionThrownByTheRememberMeServicesImplementation()
    {
        list($listener, $context, $service,,) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $exception = new AuthenticationException('cookie invalid.');
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->throwException($exception))
        ;

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($event));
        $this->assertSame($exception, $this->getLastState($listener));
    }

    public function testCheckCookiesThrowsCookieTheftExceptionIfThrownByTheRememberMeServicesImplementation()
    {
        list($listener, $context, $service,,) = $this->getListener();

        $context
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;

        $exception = new CookieTheftException('cookie was stolen.');
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->will($this->throwException($exception))
        ;

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        try {
            $listener->checkCookies($event);
        }
        catch (CookieTheftException $theft) {
            $this->assertSame($theft, $this->getLastState($listener));

            return;
        }

        $this->fail('Expected CookieTheftException was not thrown.');
    }

    public function testCheckCookiesAuthenticationManagerDoesNotChangeListenerStateWhenTokenIsNotSupported()
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

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($event));
        $this->assertNull($this->getLastState($listener));
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

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($event));
        $this->assertSame($exception, $this->getLastState($listener));
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

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->checkCookies($event));
        $this->assertSame($token, $this->getLastState($listener));
    }

    public function testUpdateCookiesIgnoresAnythingButMasterRequests()
    {
        list($listener,,,,) = $this->getListener();

        $event = $this->getEvent();
        $event
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('request_type'))
            ->will($this->returnValue('foo'))
        ;

        $response = $this->getMock('Symfony\Component\HttpFoundation\Response');

        $this->assertSame($response, $listener->updateCookies($event, $response));
    }

    public function testUpdateCookiesCallsLoginSuccessOnRememberMeServicesImplementationWhenAuthenticationWasSuccessful()
    {
        list($listener,, $service,,) = $this->getListener();

        $request = new Request();
        $response = new Response();

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->setLastState($listener, $token);

        $event = $this->getEvent();
        $event
            ->expects($this->at(0))
            ->method('get')
            ->with('request_type')
            ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST))
        ;
        $event
            ->expects($this->at(1))
            ->method('get')
            ->with('request')
            ->will($this->returnValue($request))
        ;

        $service
            ->expects($this->once())
            ->method('loginSuccess')
            ->with($this->equalTo($request), $this->equalTo($response), $this->equalTo($token))
            ->will($this->returnValue(null))
        ;

        $this->assertSame($response, $listener->updateCookies($event, $response));
    }

    public function testUpdateCookiesCallsLoginFailOnRememberMeServicesImplementationWhenAuthenticationWasNotSuccessful()
    {
        list($listener,, $service,,) = $this->getListener();

        $request = new Request();
        $response = new Response();

        $exception = new AuthenticationException('foo');
        $this->setLastState($listener, $exception);

        $event = $this->getEvent();
        $event
            ->expects($this->at(0))
            ->method('get')
            ->with('request_type')
            ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST))
        ;
        $event
            ->expects($this->at(1))
            ->method('get')
            ->with('request')
            ->will($this->returnValue($request))
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
            ->with($this->equalTo($request), $this->equalTo($response))
            ->will($this->returnValue(null))
        ;

        $this->assertSame($response, $listener->updateCookies($event, $response));
    }

    protected function setLastState($listener, $state)
    {
        $r = new \ReflectionObject($listener);
        $p = $r->getProperty('lastState');
        $p->setAccessible(true);
        $p->setValue($listener, $state);
    }

    protected function getLastState($listener)
    {
        $r = new \ReflectionObject($listener);
        $p = $r->getProperty('lastState');
        $p->setAccessible(true);

        return $p->getValue($listener);
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