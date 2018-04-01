<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Http\FirewallMap;
use Symphony\Component\HttpFoundation\Request;

class FirewallMapTest extends TestCase
{
    public function testGetListeners()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        $matchingMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $matchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(true))
        ;
        $theListener = $this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $theException = $this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();

        $map->add($matchingMatcher, array($theListener), $theException);

        $tooLateMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, array($this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array($theListener), $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithAnEntryHavingNoRequestMatcher()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        $theListener = $this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $theException = $this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();

        $map->add(null, array($theListener), $theException);

        $tooLateMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, array($this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array($theListener), $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithNoMatchingEntry()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestMatcher')->getMock();
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->will($this->returnValue(false))
        ;

        $map->add($notMatchingMatcher, array($this->getMockBuilder('Symphony\Component\Security\Http\Firewall\ListenerInterface')->getMock()));

        list($listeners, $exception) = $map->getListeners($request);

        $this->assertEquals(array(), $listeners);
        $this->assertNull($exception);
    }
}
