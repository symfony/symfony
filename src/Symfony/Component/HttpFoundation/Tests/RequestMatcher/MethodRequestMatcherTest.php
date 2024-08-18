<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\RequestMatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;

class MethodRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getData
     */
    public function test(string $requestMethod, array|string $matcherMethod, bool $isMatch)
    {
        $matcher = new MethodRequestMatcher($matcherMethod);
        $request = Request::create('', $requestMethod);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function testAlwaysMatchesOnEmptyMethod()
    {
        $matcher = new MethodRequestMatcher([]);
        $request = Request::create('https://example.com', 'POST');
        $this->assertTrue($matcher->matches($request));
    }

    public static function getData()
    {
        return [
            ['get', 'get', true],
            ['get', 'post,get', true],
            ['get', 'post, get', true],
            ['get', 'post,GET', true],
            ['get', ['get', 'post'], true],
            ['get', 'post', false],
            ['get', 'GET', true],
            ['get', ['GET', 'POST'], true],
            ['get', 'POST', false],
        ];
    }
}
