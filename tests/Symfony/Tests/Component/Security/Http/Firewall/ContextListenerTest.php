<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\ContextListener;

class ContextListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideInvalidToken
     */
    public function testInvalidTokenInSession($token)
    {
        $context = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));
        $request->expects($this->any())
            ->method('hasPreviousSession')
            ->will($this->returnValue(true));
        $request->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));
        $session->expects($this->any())
            ->method('get')
            ->with('_security_key123')
            ->will($this->returnValue(serialize($token)));
        $context->expects($this->once())
            ->method('setToken')
            ->with(null);

        $listener = new ContextListener($context, array(), 'key123');
        $listener->handle($event);
    }

    public function provideInvalidToken()
    {
        return array(
            array(new \__PHP_Incomplete_Class()),
            array(null),
        );
    }
}
