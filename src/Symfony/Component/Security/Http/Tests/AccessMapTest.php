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
use Symfony\Component\Security\Http\AccessMap;

class AccessMapTest extends TestCase
{
    public function testReturnsFirstMatchedPattern()
    {
        $request = self::createMock(Request::class);
        $requestMatcher1 = $this->getRequestMatcher($request, false);
        $requestMatcher2 = $this->getRequestMatcher($request, true);

        $map = new AccessMap();
        $map->add($requestMatcher1, ['ROLE_ADMIN'], 'http');
        $map->add($requestMatcher2, ['ROLE_USER'], 'https');

        self::assertSame([['ROLE_USER'], 'https'], $map->getPatterns($request));
    }

    public function testReturnsEmptyPatternIfNoneMatched()
    {
        $request = self::createMock(Request::class);
        $requestMatcher = $this->getRequestMatcher($request, false);

        $map = new AccessMap();
        $map->add($requestMatcher, ['ROLE_USER'], 'https');

        self::assertSame([null, null], $map->getPatterns($request));
    }

    private function getRequestMatcher($request, $matches)
    {
        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher->expects(self::once())
            ->method('matches')->with($request)
            ->willReturn($matches);

        return $requestMatcher;
    }
}
