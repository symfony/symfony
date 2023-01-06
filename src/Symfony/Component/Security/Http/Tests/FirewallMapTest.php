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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\FirewallMap;

class FirewallMapTest extends TestCase
{
    public function testGetListeners()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->createMock(RequestMatcherInterface::class);
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        $matchingMatcher = $this->createMock(RequestMatcherInterface::class);
        $matchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->willReturn(true)
        ;
        $theListener = function () {};
        $theException = $this->createMock(ExceptionListener::class);

        $map->add($matchingMatcher, [$theListener], $theException);

        $tooLateMatcher = $this->createMock(RequestMatcherInterface::class);
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        $this->assertEquals([$theListener], $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithAnEntryHavingNoRequestMatcher()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->createMock(RequestMatcherInterface::class);
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        $theListener = function () {};
        $theException = $this->createMock(ExceptionListener::class);

        $map->add(null, [$theListener], $theException);

        $tooLateMatcher = $this->createMock(RequestMatcherInterface::class);
        $tooLateMatcher
            ->expects($this->never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        $this->assertEquals([$theListener], $listeners);
        $this->assertEquals($theException, $exception);
    }

    public function testGetListenersWithNoMatchingEntry()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = $this->createMock(RequestMatcherInterface::class);
        $notMatchingMatcher
            ->expects($this->once())
            ->method('matches')
            ->with($this->equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        $this->assertEquals([], $listeners);
        $this->assertNull($exception);
    }
}
