<?php

namespace Symfony\Tests\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;

class ContextListenerTest extends \PHPUnit_Framework_TestCase
{
    // test that if the session has a token, it's set on the context
    public function testOnCoreRequestRestoresToken()
    {
        list($listener, $context, $provider, $contextKey, $logger, $dispatcher) = $this->getListener();

        list($request, $session) = $this->getRequest();

        // create a real token, mocking this and then serializing it did not work
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken('foo', 'bar', 'baz');
        $request
            ->expects($this->once())
            ->method('hasPreviousSession')
            ->will($this->returnValue(true));
        $session
            ->expects($this->once())
            ->method('get')
            ->with('_security_'.$contextKey)
            ->will($this->returnValue(serialize($token)));

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        // the end goal is that the token is set on the context
        $context
            ->expects($this->once())
            ->method('setToken')
            ->with($token)
        ;

        // since a logger is injected, debug should be called at least once
        $logger
            ->expects($this->atLeastOnce())
            ->method('debug')
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

    protected function getListener($withLogger = true)
    {
        $logger = $withLogger ? $this->getLogger() : null;

        $listener = new ContextListener(
            $context = $this->getContext(),
            array($provider = $this->getUserProvider()),
            $key = 'context_key',
            $logger,
            $dispatcher = $this->getDispatcher()
        );

        return array($listener, $context, $provider, $key, $logger, $dispatcher);
    }

    protected function getLogger()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Log\LoggerInterface');
    }

    protected function getManager()
    {
        return $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');
    }

    protected function getUserProvider()
    {
        return $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
    }

    protected function getContext()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContext')
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    protected function getDispatcher()
    {
        return $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    protected function getRequest()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session')
                        ->disableOriginalConstructor()
                        ->getMock();

        $request->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        return array($request, $session);
    }
}