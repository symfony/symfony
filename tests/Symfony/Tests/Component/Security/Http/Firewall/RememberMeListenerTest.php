<?php

namespace Symfony\Tests\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;
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

        $evm = $this->getMock('Doctrine\Common\EventManager');
        $evm
            ->expects($this->once())
            ->method('addEventListener')
            ->with($this->equalTo(array(Events::onCoreSecurity, Events::filterCoreResponse)))
        ;

        $listener->register($evm);
    }

    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptySecurityContext()
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
        $this->assertNull($listener->onCoreSecurity($this->getGetResponseEvent()));
        $this->assertNull($this->getLastState($listener));
    }

    public function testOnCoreSecurityDoesNothingWhenNoCookieIsSet()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->onCoreSecurity($event));
        $this->assertNull($this->getLastState($listener));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByTheRememberMeServicesImplementation()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->onCoreSecurity($event));
        $this->assertSame($exception, $this->getLastState($listener));
    }

    public function testOnCoreSecurityThrowsCookieTheftExceptionIfThrownByTheRememberMeServicesImplementation()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        try {
            $listener->onCoreSecurity($event);
        }
        catch (CookieTheftException $theft) {
            $this->assertSame($theft, $this->getLastState($listener));

            return;
        }

        $this->fail('Expected CookieTheftException was not thrown.');
    }

    public function testOnCoreSecurityAuthenticationManagerDoesNotChangeListenerStateWhenTokenIsNotSupported()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->onCoreSecurity($event));
        $this->assertNull($this->getLastState($listener));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->onCoreSecurity($event));
        $this->assertSame($exception, $this->getLastState($listener));
    }

    public function testOnCoreSecurity()
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(new Request()))
        ;

        $this->assertNull($this->getLastState($listener));
        $this->assertNull($listener->onCoreSecurity($event));
        $this->assertSame($token, $this->getLastState($listener));
    }

    public function testFilterCoreResponseIgnoresAnythingButMasterRequests()
    {
        list($listener,, $service,,) = $this->getListener();

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->setLastState($listener, $token);

        $event = $this->getFilterResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequestType')
            ->will($this->returnValue('foo'))
        ;

        $service
            ->expects($this->never())
            ->method('loginSuccess')
        ;

        $listener->filterCoreResponse($event);
    }

    public function testFilterCoreResponseCallsLoginSuccessOnRememberMeServicesImplementationWhenAuthenticationWasSuccessful()
    {
        list($listener,, $service,,) = $this->getListener();

        $request = new Request();
        $response = new Response();

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->setLastState($listener, $token);

        $event = $this->getFilterResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequestType')
            ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST))
        ;
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;
        $event
            ->expects($this->any())
            ->method('getResponse')
            ->will($this->returnValue($response))
        ;

        $service
            ->expects($this->once())
            ->method('loginSuccess')
            ->with($this->equalTo($request), $this->equalTo($response), $this->equalTo($token))
            ->will($this->returnValue(null))
        ;

        $listener->filterCoreResponse($event);
    }

    public function testFilterCoreResponseCallsLoginFailOnRememberMeServicesImplementationWhenAuthenticationWasNotSuccessful()
    {
        list($listener,, $service,,) = $this->getListener();

        $request = new Request();
        $response = new Response();

        $exception = new AuthenticationException('foo');
        $this->setLastState($listener, $exception);

        $event = $this->getFilterResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequestType')
            ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST))
        ;
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;
        $event
            ->expects($this->any())
            ->method('getResponse')
            ->will($this->returnValue($response))
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
            ->with($this->equalTo($request), $this->equalTo($response))
            ->will($this->returnValue(null))
        ;

        $listener->filterCoreResponse($event);
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

    protected function getGetResponseEvent()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEventArgs', array(), array(), '', false);
    }

    protected function getFilterResponseEvent()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Event\FilterResponseEventArgs', array(), array(), '', false);
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