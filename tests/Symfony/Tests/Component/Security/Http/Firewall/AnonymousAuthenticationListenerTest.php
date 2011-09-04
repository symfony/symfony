<?php

namespace Symfony\Tests\Component\Security\Http\Firewall;

use Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;

class AnonymousAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleWithContextHavingAToken()
    {
        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $context
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')))
        ;
        $context
            ->expects($this->never())
            ->method('setToken')
        ;

        $listener = new AnonymousAuthenticationListener($context, 'TheKey');
        $listener->handle($this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false));
    }

    public function testHandleWithContextHavingNoToken()
    {
        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $context
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;
        $context
            ->expects($this->once())
            ->method('setToken')
            ->with(self::logicalAnd(
                $this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\AnonymousToken'),
                $this->attributeEqualTo('key', 'TheKey')
            ))
        ;

        $listener = new AnonymousAuthenticationListener($context, 'TheKey');
        $listener->handle($this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false));
    }
}
