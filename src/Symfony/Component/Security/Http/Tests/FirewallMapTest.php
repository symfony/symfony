<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\FirewallMap;
use Symfony\Component\HttpFoundation\Request;

class FirewallMapTest extends TestCase
{
    public function testGetListeners()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        $matchingMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $matchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(true))
        ;
        $theListener = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $theException = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();

        $map->add($matchingMatcher, array($theListener), $theException);

        $tooLateMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, array($this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array($theListener), $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithAnEntryHavingNoRequestMatcher()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        $theListener = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $theException = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();

        $map->add(null, array($theListener), $theException);

        $tooLateMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, array($this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array($theListener), $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithNoMatchingEntry()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array(), $listeners);
        $this->assertNull($exception);
    }

    public function testDetachListeners()
    {
        $map = new FirewallMap();

        $request1 = new Request();
        $matchingMatcher1 = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $matchingMatcher1
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request1))
            ->will($this->returnValue(true))
        ;

        $request2 = new Request();
        $matchingMatcher2 = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestMatcher')->getMock();
        $matchingMatcher2
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request2))
            ->will($this->returnValue(true))
        ;

        $theListener = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $theException = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();

        $map->add($matchingMatcher1, array($theListener), $theException);
        $map->add($matchingMatcher2, array($theListener), $theException);

        $map->detachListeners($request1);

        list($listeners, $exception) = $map->getListeners($request1);
        $this->assertEquals(array(), $listeners);
        $this->assertNull($exception);

        list($listeners, $exception) = $map->getListeners($request2);
        $this->assertEquals(array($theListener), $listeners);
        $this->assertEquals($theException, $exception);
    }
}
