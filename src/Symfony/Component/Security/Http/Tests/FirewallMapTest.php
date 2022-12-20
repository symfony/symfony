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
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\FirewallMap;

class FirewallMapTest extends TestCase
{
    public function testGetListeners()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = self::createMock(RequestMatcher::class);
        $notMatchingMatcher
            ->expects(self::once())
            ->method('matches')
            ->with(self::equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        $matchingMatcher = self::createMock(RequestMatcher::class);
        $matchingMatcher
            ->expects(self::once())
            ->method('matches')
            ->with(self::equalTo($request))
            ->willReturn(true)
        ;
        $theListener = function () {};
        $theException = self::createMock(ExceptionListener::class);

        $map->add($matchingMatcher, [$theListener], $theException);

        $tooLateMatcher = self::createMock(RequestMatcher::class);
        $tooLateMatcher
            ->expects(self::never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        self::assertEquals([$theListener], $listeners);
        self::assertEquals($theException, $exception);
    }

    public function testGetListenersWithAnEntryHavingNoRequestMatcher()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = self::createMock(RequestMatcher::class);
        $notMatchingMatcher
            ->expects(self::once())
            ->method('matches')
            ->with(self::equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        $theListener = function () {};
        $theException = self::createMock(ExceptionListener::class);

        $map->add(null, [$theListener], $theException);

        $tooLateMatcher = self::createMock(RequestMatcher::class);
        $tooLateMatcher
            ->expects(self::never())
            ->method('matches')
        ;

        $map->add($tooLateMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        self::assertEquals([$theListener], $listeners);
        self::assertEquals($theException, $exception);
    }

    public function testGetListenersWithNoMatchingEntry()
    {
        $map = new FirewallMap();

        $request = new Request();

        $notMatchingMatcher = self::createMock(RequestMatcher::class);
        $notMatchingMatcher
            ->expects(self::once())
            ->method('matches')
            ->with(self::equalTo($request))
            ->willReturn(false)
        ;

        $map->add($notMatchingMatcher, [function () {}]);

        [$listeners, $exception] = $map->getListeners($request);

        self::assertEquals([], $listeners);
        self::assertNull($exception);
    }
}
